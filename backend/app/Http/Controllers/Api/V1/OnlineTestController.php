<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exam\SaveOnlineTestAnswerRequest;
use App\Http\Requests\Exam\SyncOnlineTestQuestionsRequest;
use App\Http\Resources\OnlineTestAttemptResource;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\TestQuestionResource;
use App\Models\ExamSubject;
use App\Models\OnlineTestAttempt;
use App\Models\Student;
use App\Services\OnlineExamService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OnlineTestController extends Controller
{
    public function __construct(private readonly OnlineExamService $onlineExams) {}

    /**
     * The questions currently attached to this specific test — scoped by
     * exam_subject_id via the OnlineTestQuestion pivot, never by subject
     * alone. A brand-new test has none until a question is created or
     * imported directly into it (see QuestionController::attachToTest() /
     * McqQuestionsImport's optional $examSubject) — subjects are no longer
     * a shared, reusable question pool across different exams/tests.
     */
    public function questions(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $this->assertCanConfigure($request, $examSubject);

        $questions = $examSubject->onlineTestQuestions()
            ->with('question.subject', 'question.options')
            ->get()
            ->map(fn ($otq) => $otq->question);

        return ApiResponse::success(QuestionResource::collection($questions));
    }

    /**
     * Question-bank management for a specific online test — same
     * Teacher-must-teach-this-subject rule as offline marks entry.
     */
    public function syncQuestions(SyncOnlineTestQuestionsRequest $request, ExamSubject $examSubject): JsonResponse
    {
        $this->assertCanConfigure($request, $examSubject);

        $this->onlineExams->syncQuestions($examSubject, $request->input('questions'));

        return ApiResponse::success(
            TestQuestionResource::collection($examSubject->onlineTestQuestions()->with('question.options')->get()),
            'Online test questions saved.'
        );
    }

    /**
     * What the acting Student can see on a "My Online Tests" page — every
     * online-mode ExamSubject for their own section, regardless of whether
     * the parent Exam has been published yet. This deliberately does NOT
     * route through Exam::scopeVisibleTo() (which hides unpublished exams
     * from Student/Parent): a test has to be *taken* to produce the marks an
     * exam gets published with, so gating discovery behind publish state
     * would make the online-exam flow impossible to reach at all.
     */
    public function myTests(Request $request): JsonResponse
    {
        $student = $this->resolveActingStudent($request);

        $examSubjects = ExamSubject::query()
            ->where('is_online', true)
            ->where('section_id', $student->current_section_id)
            ->with(['subject', 'exam', 'examSubjectGroup'])
            ->get();

        /** @var Collection<int, Collection<int, OnlineTestAttempt>> $attemptsByExamSubject */
        $attemptsByExamSubject = OnlineTestAttempt::query()
            ->where('student_id', $student->id)
            ->whereIn('exam_subject_id', $examSubjects->pluck('id'))
            ->get()
            ->groupBy('exam_subject_id');

        $rows = $examSubjects->map(function (ExamSubject $examSubject) use ($attemptsByExamSubject) {
            $attempts = $attemptsByExamSubject->get($examSubject->id, collect());
            $bestSubmitted = $attempts->where('status', 'submitted')->sortByDesc('score')->first();

            // Same visibility rule as OnlineTestAttemptResource — the score
            // this list shows a Student must not race ahead of what the
            // subject's own detail/result view is willing to reveal.
            $isDeclared = $bestSubmitted !== null
                && ($examSubject->examSubjectGroup->status(true) === 'published' || $examSubject->exam->is_published);

            return [
                'exam_subject_id' => $examSubject->id,
                'exam_name' => $examSubject->exam->name,
                'subject_name' => $examSubject->subject->name,
                'duration_minutes' => $examSubject->duration_minutes,
                'online_starts_at' => $examSubject->online_starts_at?->toIso8601String(),
                'online_ends_at' => $examSubject->online_ends_at?->toIso8601String(),
                'max_attempts' => $examSubject->max_attempts,
                'attempts_used' => $attempts->count(),
                'result_declared' => $isDeclared,
                'best_score' => $isDeclared ? $bestSubmitted?->score : null,
                'max_score' => $isDeclared ? $bestSubmitted?->max_score : null,
            ];
        });

        return ApiResponse::success($rows->values());
    }

    /**
     * The student's own current attempt — resolves the acting user's Student
     * record itself rather than trusting a client-supplied student_id, same
     * rationale as the Parent Portal endpoints.
     */
    public function start(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $student = $this->resolveActingStudent($request);

        try {
            $attempt = $this->onlineExams->startAttempt($examSubject, $student);
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), 422, $e->errors());
        }

        $questions = $examSubject->onlineTestQuestions()->with('question.options')->get();
        if ($examSubject->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        return ApiResponse::success([
            'attempt' => new OnlineTestAttemptResource($attempt, $request->user()),
            'duration_minutes' => $examSubject->duration_minutes,
            'questions' => TestQuestionResource::collection($questions->values()),
        ]);
    }

    public function saveAnswer(SaveOnlineTestAnswerRequest $request, OnlineTestAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnAttempt($request, $attempt);

        try {
            $this->onlineExams->saveAnswer(
                $attempt,
                $request->integer('question_id'),
                $request->input('selected_option_id') ? (int) $request->input('selected_option_id') : null,
                $request->user(),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), 422, $e->errors());
        }

        return ApiResponse::success(null, 'Answer saved.');
    }

    /**
     * Zero-tolerance integrity reporting: the frontend calls this the
     * instant it detects the student left the exam window (tab hidden,
     * window blur, or exited fullscreen) and this both logs the event and
     * force-submits in the same call — there's no separate "just warn"
     * step. Idempotent by design: if the attempt was already submitted by
     * the time this lands (e.g. it raced the deadline auto-submit), this
     * still logs the event but simply returns the attempt as-is rather
     * than erroring, since nothing actually went wrong from the caller's
     * point of view — the exam is over either way.
     */
    public function reportViolation(Request $request, OnlineTestAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnAttempt($request, $attempt);

        $data = $request->validate([
            'event_type' => ['required', 'string', 'in:tab_hidden,window_blur,fullscreen_exit'],
        ]);

        $attempt = $this->onlineExams->recordViolationAndSubmit($attempt, $data['event_type'], $request->user());
        $attempt->load(['answers.question.options', 'examSubject.examSubjectGroup.exam']);

        return ApiResponse::success(new OnlineTestAttemptResource($attempt, $request->user()), 'Left the exam window — test submitted.');
    }

    /**
     * Called on a short interval by a genuinely open exam tab, purely to
     * keep the login lock (see OnlineExamService::hasActiveExamLock()) alive
     * for as long as the student is actually still there — see
     * OnlineExamService::heartbeat() for what happens once these stop.
     */
    public function heartbeat(Request $request, OnlineTestAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnAttempt($request, $attempt);

        $this->onlineExams->heartbeat($attempt);

        return ApiResponse::success(null);
    }

    /**
     * Lightweight, pre-attempt metadata for the waiting-room screen — title,
     * timing, and the join-window bounds, but never the question list
     * itself (that only exists once start() actually creates an attempt).
     * server_time lets the frontend correct for its own clock being wrong
     * rather than trusting the browser's Date.now() outright.
     */
    public function onlineStatus(Request $request, ExamSubject $examSubject): JsonResponse
    {
        $student = $this->resolveActingStudent($request);
        abort_unless($student->current_section_id === $examSubject->section_id, 403, 'You are not enrolled in this section.');

        return ApiResponse::success([
            'exam_name' => $examSubject->exam->name,
            'subject_name' => $examSubject->subject->name,
            'duration_minutes' => $examSubject->duration_minutes,
            'online_starts_at' => $examSubject->online_starts_at?->toIso8601String(),
            'online_ends_at' => $examSubject->online_ends_at?->toIso8601String(),
            'early_access_minutes' => $examSubject->early_access_minutes,
            'late_join_grace_minutes' => $examSubject->late_join_grace_minutes,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request, OnlineTestAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnAttempt($request, $attempt);

        try {
            $attempt = $this->onlineExams->submitAttempt($attempt, $request->user());
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), 422, $e->errors());
        }

        $attempt->load(['answers.question.options', 'examSubject.examSubjectGroup.exam']);

        return ApiResponse::success(new OnlineTestAttemptResource($attempt, $request->user()), 'Test submitted.');
    }

    public function show(Request $request, OnlineTestAttempt $attempt): JsonResponse
    {
        $this->authorizeOwnAttempt($request, $attempt, allowStaffView: true);

        $attempt->load(['answers.question.options', 'examSubject.examSubjectGroup.exam']);

        return ApiResponse::success(new OnlineTestAttemptResource($attempt, $request->user()));
    }

    /**
     * Same rule as ExamMarkController::assertSubjectMarkable() — configuring
     * an online test is a form of authoring that subject's assessment, so it
     * requires the same "actually teaches this subject/section" check for
     * non-admin roles, gated behind online-exams.configure.
     */
    private function assertCanConfigure(Request $request, ExamSubject $examSubject): void
    {
        $user = $request->user();
        abort_unless($user->can('online-exams.configure'), 403);

        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return;
        }

        abort_unless($examSubject->isTaughtBy($user), 403, 'You are not assigned to teach this subject.');
    }

    private function resolveActingStudent(Request $request): Student
    {
        $student = Student::query()->where('user_id', $request->user()->id)->first();
        abort_unless($student, 403, 'No student profile is linked to your account.');

        return $student;
    }

    /**
     * `exam-marks.view` alone isn't a safe "is staff" check — every Student
     * also holds it (for viewing their own marks elsewhere, correctly scoped
     * via ExamMark::visibleTo there). Without excluding Student/Parent here,
     * any student could view any other student's in-progress attempt.
     */
    private function authorizeOwnAttempt(Request $request, OnlineTestAttempt $attempt, bool $allowStaffView = false): void
    {
        $user = $request->user();

        if ($allowStaffView && ! $user->hasAnyRole(['Student', 'Parent']) && $user->can('exam-marks.view')) {
            return;
        }

        $student = Student::query()->where('user_id', $user->id)->first();
        abort_unless($student && $attempt->student_id === $student->id, 403);
    }
}
