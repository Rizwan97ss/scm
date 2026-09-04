<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CourseMaterials\StoreCourseMaterialRequest;
use App\Http\Requests\CourseMaterials\UpdateCourseMaterialRequest;
use App\Http\Resources\CourseMaterialResource;
use App\Models\CourseMaterial;
use App\Models\CourseMaterialProgress;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CourseMaterialController extends CrudController
{
    private const WITH = ['section', 'subject', 'teacher'];

    protected function modelClass(): string
    {
        return CourseMaterial::class;
    }

    protected function resourceClass(): string
    {
        return CourseMaterialResource::class;
    }

    /** Overrides CrudController::index() the same way HomeworkController does — row-level scoping via visibleTo(), plus the acting student's own progress eager-loaded so the list shows viewed/completed state without a second request per row. */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CourseMaterial::class);

        $query = CourseMaterial::query()->visibleTo($request->user())->with(self::WITH);
        $this->withOwnProgress($query, $request);

        $paginator = QueryBuilder::for($query)
            ->allowedFilters(AllowedFilter::exact('section_id'), AllowedFilter::exact('subject_id'), AllowedFilter::exact('type'))
            ->allowedSorts('title', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(CourseMaterialResource::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    public function show(int $id): JsonResponse
    {
        $request = request();
        $query = CourseMaterial::query()->with(self::WITH)->visibleTo($request->user());
        $this->withOwnProgress($query, $request);
        $material = $query->findOrFail($id);

        $this->authorize('view', $material);

        return ApiResponse::success(new CourseMaterialResource($material));
    }

    public function store(StoreCourseMaterialRequest $request): JsonResponse
    {
        $this->authorize('create', CourseMaterial::class);

        $material = new CourseMaterial([
            ...$request->safe()->only(['section_id', 'subject_id', 'title', 'description', 'type', 'url', 'is_published']),
            'teacher_id' => $request->user()->id,
        ]);

        $this->assertCanManage($request, $material);
        $material->save();

        return ApiResponse::created(new CourseMaterialResource($material->load(self::WITH)));
    }

    public function update(UpdateCourseMaterialRequest $request, CourseMaterial $courseMaterial): JsonResponse
    {
        $this->authorize('update', $courseMaterial);
        $this->assertCanManage($request, $courseMaterial);

        $courseMaterial->update($request->safe()->only(['section_id', 'subject_id', 'title', 'description', 'type', 'url', 'is_published']));

        return ApiResponse::success(new CourseMaterialResource($courseMaterial->load(self::WITH)));
    }

    public function destroy(int $id): JsonResponse
    {
        $request = request();
        $material = CourseMaterial::query()->findOrFail($id);

        $this->authorize('delete', $material);
        $this->assertCanManage($request, $material);

        $material->delete();

        return ApiResponse::noContent();
    }

    public function storeAttachment(Request $request, CourseMaterial $courseMaterial): JsonResponse
    {
        $this->authorize('update', $courseMaterial);
        $this->assertCanManage($request, $courseMaterial);

        $request->validate(['file' => ['required', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,mp4,mov,webm']]);

        $media = $courseMaterial->addMediaFromRequest('file')->toMediaCollection('attachments');

        return ApiResponse::created(['id' => $media->id, 'file_name' => $media->file_name, 'size' => $media->size, 'url' => route('api.v1.media.show', $media)]);
    }

    public function destroyAttachment(Request $request, CourseMaterial $courseMaterial, int $media): JsonResponse
    {
        $this->authorize('update', $courseMaterial);
        $this->assertCanManage($request, $courseMaterial);

        $courseMaterial->media()->findOrFail($media)->delete();

        return ApiResponse::noContent();
    }

    /**
     * The acting student marks their own progress — resolved from the
     * authenticated user, never a client-supplied student_id, same
     * rationale as HomeworkSubmissionController::submit(). Upserts rather
     * than requiring a separate action: viewing again just refreshes
     * viewed_at, it doesn't create a second row.
     */
    public function markProgress(Request $request, CourseMaterial $courseMaterial): JsonResponse
    {
        $this->authorize('view', $courseMaterial);

        $student = Student::query()->where('user_id', $request->user()->id)->first();
        abort_unless($student, 403, 'No student profile is linked to your account.');
        abort_unless($student->current_section_id === $courseMaterial->section_id, 403, 'This material is not assigned to your section.');

        $request->validate(['completed' => ['sometimes', 'boolean']]);

        $progress = CourseMaterialProgress::query()->firstOrNew(['course_material_id' => $courseMaterial->id, 'student_id' => $student->id]);
        $progress->viewed_at ??= now();
        if ($request->boolean('completed')) {
            $progress->completed_at = now();
        }
        $progress->save();

        return ApiResponse::success(['viewed_at' => $progress->viewed_at?->toIso8601String(), 'completed_at' => $progress->completed_at?->toIso8601String()]);
    }

    /**
     * A Teacher/Class Teacher may only manage material for a subject
     * they're actually assigned to teach in this exact section — same rule
     * as HomeworkController::assertCanManage().
     */
    private function assertCanManage(Request $request, CourseMaterial $material): void
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['Teacher', 'Class Teacher']) || $user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($material->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }

    private function withOwnProgress(Builder $query, Request $request): void
    {
        $user = $request->user();

        if ($user->hasRole('Student')) {
            $studentId = Student::query()->where('user_id', $user->id)->value('id');
            $query->with(['progress' => fn ($q) => $q->where('student_id', $studentId)]);
        }
    }
}
