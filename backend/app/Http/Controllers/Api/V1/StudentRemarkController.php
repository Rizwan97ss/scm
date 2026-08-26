<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StudentRemark\StoreStudentRemarkRequest;
use App\Http\Requests\StudentRemark\UpdateStudentRemarkRequest;
use App\Http\Resources\StudentRemarkResource;
use App\Models\Student;
use App\Models\StudentRemark;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StudentRemarkController extends CrudController
{
    private const WITH = ['author'];

    protected function modelClass(): string
    {
        return StudentRemark::class;
    }

    protected function resourceClass(): string
    {
        return StudentRemarkResource::class;
    }

    /**
     * Overrides CrudController::index() — same reason as every other
     * row-scoped module: the blanket remarks.view permission alone would
     * let a Student/Parent (or a Teacher outside the relevant section) list
     * every remark in the school. filter[student_id] is how the Student
     * Profile/Parent Portal tabs scope this down to one student.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StudentRemark::class);

        $paginator = QueryBuilder::for(StudentRemark::query()->visibleTo($request->user())->with(self::WITH))
            ->allowedFilters(AllowedFilter::exact('student_id'), AllowedFilter::exact('category'))
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 20))
            ->appends($request->query());

        return ApiResponse::success(StudentRemarkResource::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    /**
     * Overrides CrudController::show() — same reason as index(): without
     * scoping via visibleTo() first, the blanket remarks.view permission
     * would let a Student/Parent fetch any remark in the school by ID,
     * including ones about a different family's child and ones marked
     * !visible_to_guardian.
     */
    public function show(int $id): JsonResponse
    {
        $request = request();
        $studentRemark = StudentRemark::query()->with(self::WITH)->visibleTo($request->user())->findOrFail($id);

        $this->authorize('view', $studentRemark);

        return ApiResponse::success(new StudentRemarkResource($studentRemark));
    }

    public function store(StoreStudentRemarkRequest $request): JsonResponse
    {
        $this->authorize('create', StudentRemark::class);

        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->assertCanWriteAbout($request, $student);

        $remark = StudentRemark::query()->create([
            ...$request->safe()->only(['category', 'body', 'visible_to_guardian']),
            'student_id' => $student->id,
            'author_id' => $request->user()->id,
            'section_id' => $student->current_section_id,
        ]);

        return ApiResponse::created(new StudentRemarkResource($remark->load(self::WITH)));
    }

    public function update(UpdateStudentRemarkRequest $request, StudentRemark $studentRemark): JsonResponse
    {
        $this->authorize('update', $studentRemark);
        $this->assertCanWriteAbout($request, $studentRemark->student);

        $studentRemark->update($request->safe()->only(['category', 'body', 'visible_to_guardian']));

        return ApiResponse::success(new StudentRemarkResource($studentRemark->load(self::WITH)));
    }

    public function destroy(int $id): JsonResponse
    {
        $request = request();
        $studentRemark = StudentRemark::query()->findOrFail($id);

        $this->authorize('delete', $studentRemark);
        $this->assertCanWriteAbout($request, $studentRemark->student);

        $studentRemark->delete();

        return ApiResponse::noContent();
    }

    /**
     * A Teacher/Class Teacher may only write remarks about a student
     * currently in a section they teach or lead — the same "visible
     * students" set as Student::scopeVisibleTo(), reused directly rather
     * than re-deriving it. School Admin/Principal are unrestricted.
     */
    private function assertCanWriteAbout(Request $request, Student $student): void
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Teacher', 'Class Teacher']) || $user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        $allowed = Student::query()->whereKey($student->id)->visibleTo($user)->exists();
        abort_unless($allowed, 403, 'You are not assigned to this student\'s section.');
    }
}
