<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Exam\StoreExamRequest;
use App\Http\Requests\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Http\Resources\ExamSubjectGroupResource;
use App\Http\Resources\SubjectResultResource;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\ExamSubjectGroup;
use App\Models\GradingScale;
use App\Models\Section;
use App\Models\Student;
use App\Services\ExamService;
use App\Services\SubjectResultService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class ExamController extends CrudController
{
    protected array $allowedFilters = ['academic_year_id', 'term_id'];

    protected array $allowedSorts = ['name', 'created_at'];

    protected array $with = ['examType', 'examSubjectGroups.subject', 'examSubjectGroups.section', 'examSubjectGroups.components.assessmentComponentType'];

    public function __construct(
        private readonly ExamService $exams,
        private readonly SubjectResultService $subjectResults,
    ) {}

    protected function modelClass(): string
    {
        return Exam::class;
    }

    protected function resourceClass(): string
    {
        return ExamResource::class;
    }

    /**
     * Overrides CrudController::index() — the generic version has no
     * row-scoping, which would let a Student/Parent list every exam in the
     * school (unpublished, unrelated sections) via the blanket exams.view
     * permission. See Exam::scopeVisibleTo().
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Exam::class);

        $paginator = QueryBuilder::for(Exam::query()->visibleTo($request->user()))
            ->allowedFilters(...$this->allowedFilters)
            ->allowedSorts(...$this->allowedSorts)
            ->defaultSort($this->defaultSort)
            ->with($this->with)
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return ApiResponse::success(ExamResource::collection($paginator->items()), meta: $this->paginationMeta($paginator));
    }

    /**
     * Overrides CrudController::show() for the same reason as index() — a
     * Student/Parent must not be able to fetch an unpublished/unrelated exam
     * by guessing its ID.
     */
    public function show(int $id): JsonResponse
    {
        $exam = Exam::query()->with($this->with)->visibleTo(request()->user())->findOrFail($id);

        $this->authorize('view', $exam);

        return ApiResponse::success(new ExamResource($exam));
    }

    public function store(StoreExamRequest $request): JsonResponse
    {
        $this->authorize('create', Exam::class);

        $exam = DB::transaction(function () use ($request) {
            $exam = Exam::query()->create($request->safe()->only(['academic_year_id', 'term_id', 'exam_type_id', 'name', 'weight']));

            foreach ($request->input('exam_subject_groups', []) as $groupData) {
                $this->createGroup($exam, $groupData);
            }

            return $exam;
        });

        return ApiResponse::created(new ExamResource($exam->load($this->with)));
    }

    /**
     * Groups (and their components) are upserted — a group matched by
     * subject_id+section_id, a component matched by
     * assessment_component_type_id within that group — never
     * wholesale-replaced, the same guarantee this endpoint has always made:
     * a component that already has ExamMarks entered against it must
     * survive an unrelated edit to the exam's name, since deleting it would
     * cascade-delete those marks. Removing a component is a separate,
     * explicit action — see destroyComponent().
     */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $this->authorize('update', $exam);

        DB::transaction(function () use ($request, $exam) {
            $exam->update($request->safe()->only(['academic_year_id', 'term_id', 'exam_type_id', 'name', 'weight']));

            foreach ($request->input('exam_subject_groups', []) as $groupData) {
                $existingGroup = $exam->examSubjectGroups()
                    ->where('subject_id', $groupData['subject_id'])
                    ->where('section_id', $groupData['section_id'])
                    ->first();

                $group = $existingGroup
                    ? tap($existingGroup)->update($this->groupAttributes($groupData))
                    : $this->createGroup($exam, $groupData, false);

                foreach ($groupData['components'] as $componentData) {
                    $existingComponent = $group->components()
                        ->where('assessment_component_type_id', $componentData['assessment_component_type_id'])
                        ->first();

                    if ($existingComponent) {
                        $existingComponent->update($this->componentAttributes($componentData));
                    } else {
                        $this->createComponent($exam, $group, $componentData);
                    }
                }
            }
        });

        return ApiResponse::success(new ExamResource($exam->load($this->with)));
    }

    public function destroyComponent(Exam $exam, ExamSubjectGroup $group, ExamSubject $examSubject): JsonResponse
    {
        $this->authorize('update', $exam);
        abort_unless($group->exam_id === $exam->id, 404);
        abort_unless($examSubject->exam_subject_group_id === $group->id, 404);

        $examSubject->delete();

        return ApiResponse::noContent();
    }

    public function publish(Request $request, Exam $exam): JsonResponse
    {
        $this->authorize('publish', $exam);

        return ApiResponse::success(new ExamResource($this->exams->publish($exam, $request->user())->load($this->with)), 'Exam published.');
    }

    public function unpublish(Exam $exam): JsonResponse
    {
        $this->authorize('publish', $exam);

        return ApiResponse::success(new ExamResource($this->exams->unpublish($exam)->load($this->with)), 'Exam unpublished.');
    }

    /**
     * Declares just this one subject's result early — see ExamSubjectGroup::status() and
     * ExamService::reportCard() for how this composes with the whole-exam publish flag.
     *
     * Class-wide, not per-student — unlike groupResult()/reportCard(), there's no single
     * student in view here, so the response is the group itself (ExamSubjectGroupResource),
     * not a SubjectResultResource, which would need a student_id nobody has at this point.
     */
    public function publishGroup(Request $request, Exam $exam, ExamSubjectGroup $group): JsonResponse
    {
        abort_unless($group->exam_id === $exam->id, 404);
        $this->assertCanPublishGroup($request, $group);
        $group = $this->exams->publishGroup($group, $request->user());

        return ApiResponse::success(new ExamSubjectGroupResource($group->load(['subject', 'section', 'components.assessmentComponentType'])), 'Result declared for this subject.');
    }

    public function unpublishGroup(Request $request, Exam $exam, ExamSubjectGroup $group): JsonResponse
    {
        abort_unless($group->exam_id === $exam->id, 404);
        $this->assertCanPublishGroup($request, $group);
        $group = $this->exams->unpublishGroup($group);

        return ApiResponse::success(new ExamSubjectGroupResource($group->load(['subject', 'section', 'components.assessmentComponentType'])), 'Subject result un-declared.');
    }

    /** The combined component breakdown + total for one subject group (?student_id=) — same shape a Student/Parent sees once declared, used by staff to preview it beforehand. */
    public function groupResult(Request $request, Exam $exam, ExamSubjectGroup $group): JsonResponse
    {
        abort_unless($group->exam_id === $exam->id, 404);

        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        $result = $this->subjectResultFor($request, $group, $student);

        return ApiResponse::success(new SubjectResultResource($result));
    }

    public function reportCard(Request $request, Exam $exam): JsonResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        return ApiResponse::success($this->exams->reportCard($exam, $student, $request->user()));
    }

    public function reportCardPdf(Request $request, Exam $exam): Response
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        $data = $this->exams->reportCard($exam, $student, $request->user());

        $pdf = Pdf::loadView('pdf.report-card', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        $fileName = str($data['student']['full_name'].'-'.$exam->name)->slug().'-report-card.pdf';

        return $pdf->download($fileName);
    }

    private function createGroup(Exam $exam, array $data, bool $withComponents = true): ExamSubjectGroup
    {
        $group = ExamSubjectGroup::query()->create([
            'exam_id' => $exam->id,
            'subject_id' => $data['subject_id'],
            'section_id' => $data['section_id'],
            ...$this->groupAttributes($data),
        ]);

        if ($withComponents) {
            foreach ($data['components'] as $componentData) {
                $this->createComponent($exam, $group, $componentData);
            }
        }

        return $group;
    }

    private function createComponent(Exam $exam, ExamSubjectGroup $group, array $data): ExamSubject
    {
        return ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'exam_subject_group_id' => $group->id,
            'subject_id' => $group->subject_id,
            'section_id' => $group->section_id,
            ...$this->componentAttributes($data),
        ]);
    }

    private function groupAttributes(array $data): array
    {
        return [
            'grading_scale_id' => $data['grading_scale_id'] ?? null,
            'passing_marks' => $data['passing_marks'] ?? null,
        ];
    }

    private function componentAttributes(array $data): array
    {
        return [
            'assessment_component_type_id' => $data['assessment_component_type_id'],
            'max_marks' => $data['max_marks'],
            'sequence' => $data['sequence'] ?? 0,
            'exam_date' => $data['exam_date'] ?? null,
            'is_online' => $data['is_online'] ?? false,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'online_starts_at' => $data['online_starts_at'] ?? null,
            'online_ends_at' => $data['online_ends_at'] ?? null,
            'shuffle_questions' => $data['shuffle_questions'] ?? true,
            'max_attempts' => $data['max_attempts'] ?? 1,
            'early_access_minutes' => $data['early_access_minutes'] ?? 0,
            'late_join_grace_minutes' => $data['late_join_grace_minutes'] ?? 2,
        ];
    }

    private function subjectResultFor(Request $request, ExamSubjectGroup $group, ?Student $student = null): array
    {
        $student ??= Student::query()->findOrFail($request->integer('student_id'));
        $defaultScale = GradingScale::query()->where('is_default', true)->with('gradeBands')->first();

        return $this->subjectResults->forGroup($group, $student, $defaultScale);
    }

    /**
     * Same idiom as ExamMarkController::assertSubjectMarkable()/
     * OnlineTestController::assertCanConfigure() — Admin/Principal/Super
     * Admin always; otherwise only the class teacher of the group's own
     * section, matching requirement 3's "Class Teacher can view/declare for
     * their authorized classes."
     */
    private function assertCanPublishGroup(Request $request, ExamSubjectGroup $group): void
    {
        $user = $request->user();
        abort_unless($user->can('exam-marks.publish'), 403);

        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless(
            Section::query()->where('id', $group->section_id)->where('class_teacher_id', $user->id)->exists(),
            403,
            'You are not the class teacher of this section.'
        );
    }
}
