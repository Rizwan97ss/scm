<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Homework\StoreHomeworkRequest;
use App\Http\Requests\Homework\UpdateHomeworkRequest;
use App\Http\Resources\HomeworkResource;
use App\Models\Homework;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class HomeworkController extends CrudController
{
    private const WITH = ['section', 'subject', 'teacher'];

    protected function modelClass(): string
    {
        return Homework::class;
    }

    protected function resourceClass(): string
    {
        return HomeworkResource::class;
    }

    /**
     * Overrides CrudController::index() for the same reason ExamController
     * does: the blanket homework.view permission alone would let a
     * Student/Parent list every school's homework via row-level scoping
     * left unapplied. For a Student/Parent viewer, also eager-loads their
     * own submission (if any) so the list can show submitted/graded status
     * without a second request per row.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Homework::class);

        $query = Homework::query()->visibleTo($request->user())->with(self::WITH);
        $this->withOwnSubmission($query, $request);

        $paginator = QueryBuilder::for($query)
            ->allowedFilters(AllowedFilter::exact('section_id'), AllowedFilter::exact('subject_id'))
            ->allowedSorts('due_date', 'created_at')
            ->defaultSort('-due_date')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(HomeworkResource::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    public function show(int $id): JsonResponse
    {
        $request = request();
        $query = Homework::query()->with(self::WITH)->visibleTo($request->user());
        $this->withOwnSubmission($query, $request);
        $homework = $query->findOrFail($id);

        $this->authorize('view', $homework);

        return ApiResponse::success(new HomeworkResource($homework));
    }

    public function store(StoreHomeworkRequest $request): JsonResponse
    {
        $this->authorize('create', Homework::class);

        $homework = new Homework([
            ...$request->safe()->only(['academic_year_id', 'section_id', 'subject_id', 'title', 'description', 'due_date', 'max_score']),
            'teacher_id' => $request->user()->id,
        ]);

        $this->assertCanManage($request, $homework);
        $homework->save();

        return ApiResponse::created(new HomeworkResource($homework->load(self::WITH)));
    }

    public function update(UpdateHomeworkRequest $request, Homework $homework): JsonResponse
    {
        $this->authorize('update', $homework);
        $this->assertCanManage($request, $homework);

        $homework->update($request->safe()->only(['academic_year_id', 'section_id', 'subject_id', 'title', 'description', 'due_date', 'max_score']));

        return ApiResponse::success(new HomeworkResource($homework->load(self::WITH)));
    }

    public function destroy(int $id): JsonResponse
    {
        $request = request();
        $homework = Homework::query()->findOrFail($id);

        $this->authorize('delete', $homework);
        $this->assertCanManage($request, $homework);

        $homework->delete();

        return ApiResponse::noContent();
    }

    public function storeAttachment(Request $request, Homework $homework): JsonResponse
    {
        $this->authorize('update', $homework);
        $this->assertCanManage($request, $homework);

        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx']]);

        $media = $homework->addMediaFromRequest('file')->toMediaCollection('attachments');

        return ApiResponse::created(['id' => $media->id, 'file_name' => $media->file_name, 'size' => $media->size, 'url' => route('api.v1.media.show', $media)]);
    }

    public function destroyAttachment(Request $request, Homework $homework, int $media): JsonResponse
    {
        $this->authorize('update', $homework);
        $this->assertCanManage($request, $homework);

        $homework->media()->findOrFail($media)->delete();

        return ApiResponse::noContent();
    }

    /**
     * A Teacher/Class Teacher may only manage homework for a subject they're
     * actually assigned to teach in this exact section — same rule as
     * ExamSubject::isTaughtBy() / StudentAttendanceController's section
     * guard. School Admin (the only other role holding homework.create/
     * edit/delete) is unrestricted.
     */
    private function assertCanManage(Request $request, Homework $homework): void
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Teacher', 'Class Teacher']) || $user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($homework->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }

    /**
     * Lets a Student/Parent viewer see their own submission status inline
     * with each homework row, without exposing every other student's
     * submissions (which the generic 'submissions' relation would).
     */
    private function withOwnSubmission(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($user->hasRole('Student')) {
            $studentId = Student::query()->where('user_id', $user->id)->value('id');
            $query->with(['submissions' => fn ($q) => $q->where('student_id', $studentId)]);
        }
    }
}
