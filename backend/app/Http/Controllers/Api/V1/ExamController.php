<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Exam\StoreExamRequest;
use App\Http\Requests\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Student;
use App\Services\ExamService;
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

    protected array $with = ['examSubjects.subject', 'examSubjects.section'];

    public function __construct(private readonly ExamService $exams) {}

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
            $exam = Exam::query()->create($request->safe()->only(['academic_year_id', 'term_id', 'name', 'weight']));

            foreach ($request->input('exam_subjects', []) as $examSubject) {
                $this->createExamSubject($exam, $examSubject);
            }

            return $exam;
        });

        return ApiResponse::created(new ExamResource($exam->load($this->with)));
    }

    /**
     * exam_subjects are upserted (matched by subject_id+section_id), never
     * wholesale-replaced like GradingScale's bands — an ExamSubject row that
     * already has ExamMarks entered against it must survive an unrelated
     * edit to the exam's name, since deleting it would cascade-delete those
     * marks. Removing an exam_subject is a separate, explicit action —
     * see destroyExamSubject().
     */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $this->authorize('update', $exam);

        DB::transaction(function () use ($request, $exam) {
            $exam->update($request->safe()->only(['academic_year_id', 'term_id', 'name', 'weight']));

            foreach ($request->input('exam_subjects', []) as $data) {
                $existing = $exam->examSubjects()
                    ->where('subject_id', $data['subject_id'])
                    ->where('section_id', $data['section_id'])
                    ->first();

                if ($existing) {
                    $existing->update($this->examSubjectAttributes($data));
                } else {
                    $this->createExamSubject($exam, $data);
                }
            }
        });

        return ApiResponse::success(new ExamResource($exam->load($this->with)));
    }

    public function destroyExamSubject(Exam $exam, ExamSubject $examSubject): JsonResponse
    {
        $this->authorize('update', $exam);
        abort_unless($examSubject->exam_id === $exam->id, 404);

        $examSubject->delete();

        return ApiResponse::noContent();
    }

    public function publish(Exam $exam): JsonResponse
    {
        $this->authorize('publish', $exam);

        return ApiResponse::success(new ExamResource($this->exams->publish($exam)->load($this->with)), 'Exam published.');
    }

    public function unpublish(Exam $exam): JsonResponse
    {
        $this->authorize('publish', $exam);

        return ApiResponse::success(new ExamResource($this->exams->unpublish($exam)->load($this->with)), 'Exam unpublished.');
    }

    public function reportCard(Request $request, Exam $exam): JsonResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        abort_if(
            ! $exam->is_published && $request->user()->hasAnyRole(['Student', 'Parent']),
            403,
            'This exam has not been published yet.'
        );

        return ApiResponse::success($this->exams->reportCard($exam, $student));
    }

    public function reportCardPdf(Request $request, Exam $exam): Response
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->authorize('view', $student);

        abort_if(
            ! $exam->is_published && $request->user()->hasAnyRole(['Student', 'Parent']),
            403,
            'This exam has not been published yet.'
        );

        $data = $this->exams->reportCard($exam, $student);

        $pdf = Pdf::loadView('pdf.report-card', [
            'data' => $data,
            'schoolName' => tenant()->name,
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        $fileName = str($data['student']['full_name'].'-'.$exam->name)->slug().'-report-card.pdf';

        return $pdf->download($fileName);
    }

    private function createExamSubject(Exam $exam, array $data): ExamSubject
    {
        return ExamSubject::query()->create([
            'exam_id' => $exam->id,
            'subject_id' => $data['subject_id'],
            'section_id' => $data['section_id'],
            ...$this->examSubjectAttributes($data),
        ]);
    }

    private function examSubjectAttributes(array $data): array
    {
        return [
            'grading_scale_id' => $data['grading_scale_id'] ?? null,
            'max_marks' => $data['max_marks'],
            'passing_marks' => $data['passing_marks'] ?? null,
            'exam_date' => $data['exam_date'] ?? null,
            'is_online' => $data['is_online'] ?? false,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'online_starts_at' => $data['online_starts_at'] ?? null,
            'online_ends_at' => $data['online_ends_at'] ?? null,
            'shuffle_questions' => $data['shuffle_questions'] ?? true,
            'max_attempts' => $data['max_attempts'] ?? 1,
        ];
    }
}
