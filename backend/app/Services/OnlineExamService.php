<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\OnlineTestAnswer;
use App\Models\OnlineTestAttempt;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OnlineExamService
{
    /**
     * How long a heartbeat-less attempt is still treated as "live" for the
     * login lock (see hasActiveExamLock()) — long enough to absorb one
     * missed beat at the frontend's own interval, short enough that a
     * genuine disconnect (crash, closed laptop, dead network) lets the
     * student back in within well under a minute rather than needing an
     * admin to intervene.
     */
    private const LOCK_LIVENESS_SECONDS = 45;

    /**
     * Replaces the online test's question list wholesale — safe even with
     * existing attempts, since OnlineTestAnswer references question_id
     * directly, not the pivot row, so re-attaching the same question keeps
     * past answers intact.
     *
     * @param  array<int, array{question_id: int, marks?: float|null}>  $questions
     */
    public function syncQuestions(ExamSubject $examSubject, array $questions): void
    {
        DB::transaction(function () use ($examSubject, $questions) {
            $examSubject->onlineTestQuestions()->delete();

            foreach ($questions as $index => $q) {
                $examSubject->onlineTestQuestions()->create([
                    'question_id' => $q['question_id'],
                    'marks' => $q['marks'] ?? null,
                    'sequence' => $index,
                ]);
            }
        });
    }

    /**
     * Eligibility is checked here, not a Policy — it's a business rule
     * (enrolled in the section, within the time window, attempts remaining),
     * not a role/permission concern.
     *
     * The join window is deliberately asymmetric: early_access_minutes lets
     * a student open and start up to N minutes before the scheduled start
     * (settling in, reading instructions) but late_join_grace_minutes is
     * short and one-directional — once that grace has passed, a student who
     * never started at all is locked out entirely, same as walking into a
     * physical exam hall after the papers were handed out. Resuming an
     * attempt that already legitimately started is a completely separate
     * question, governed only by that attempt's own deadline (see
     * attemptDeadline()), never by the join window — a student who started
     * on time but reloads the page after online_starts_at has since passed
     * must still be able to get back into their own in-progress attempt.
     */
    public function startAttempt(ExamSubject $examSubject, Student $student): OnlineTestAttempt
    {
        throw_unless($examSubject->is_online, ValidationException::withMessages(['exam_subject' => 'This subject is not configured for an online test.']));
        throw_unless($student->current_section_id === $examSubject->section_id, ValidationException::withMessages(['student' => 'You are not enrolled in this section.']));

        $inProgress = OnlineTestAttempt::query()
            ->where('exam_subject_id', $examSubject->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if ($inProgress) {
            $deadline = $this->attemptDeadline($inProgress);
            if ($deadline && now()->gt($deadline)) {
                throw ValidationException::withMessages(['window' => 'Your time for this test has ended.']);
            }

            $inProgress->update(['last_seen_at' => now()]);

            return $inProgress;
        }

        // Only a genuinely new attempt is gated by the join window — resuming
        // (above) already returned.
        $now = now();
        $earlyOpensAt = $examSubject->online_starts_at?->copy()->subMinutes($examSubject->early_access_minutes);
        $lateJoinCutoff = $examSubject->online_starts_at?->copy()->addMinutes($examSubject->late_join_grace_minutes);

        if ($earlyOpensAt && $now->lt($earlyOpensAt)) {
            throw ValidationException::withMessages(['window' => 'This test has not opened yet.']);
        }
        if ($lateJoinCutoff && $now->gt($lateJoinCutoff)) {
            throw ValidationException::withMessages(['window' => 'This test has already started and can no longer be joined.']);
        }
        if ($examSubject->online_ends_at && $now->gt($examSubject->online_ends_at)) {
            throw ValidationException::withMessages(['window' => 'This test has closed.']);
        }

        $existingAttempts = OnlineTestAttempt::query()
            ->where('exam_subject_id', $examSubject->id)
            ->where('student_id', $student->id)
            ->count();

        throw_if($existingAttempts >= $examSubject->max_attempts, ValidationException::withMessages(['attempts' => 'No attempts remaining.']));

        return OnlineTestAttempt::query()->create([
            'exam_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'attempt_number' => $existingAttempts + 1,
            'status' => 'in_progress',
            'started_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    /**
     * Keeps this attempt "live" for the login lock below — called by the
     * frontend on a short interval while the tab is genuinely open. A no-op
     * once the attempt is no longer in_progress; a heartbeat racing a
     * just-submitted attempt isn't an error from the caller's point of view.
     */
    public function heartbeat(OnlineTestAttempt $attempt): void
    {
        if ($attempt->status === 'in_progress') {
            $attempt->update(['last_seen_at' => now()]);
        }
    }

    /**
     * The exam-integrity login lock: while a student has an in-progress
     * attempt with a recent heartbeat, a second login for that same account
     * is refused (see LoginRequest::authenticate()) — someone else can't log
     * in with shared/leaked credentials while the real student is mid-exam.
     * Liveness, not mere existence, is what's checked: once the heartbeat
     * goes stale (tab closed, crash, dead connection) the lock lifts on its
     * own within LOCK_LIVENESS_SECONDS, so the legitimate student can always
     * log back in to resume without needing anyone to intervene.
     */
    public function hasActiveExamLock(User $user): bool
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        if (! $student) {
            return false;
        }

        return OnlineTestAttempt::query()
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->where('last_seen_at', '>', now()->subSeconds(self::LOCK_LIVENESS_SECONDS))
            ->exists();
    }

    public function saveAnswer(OnlineTestAttempt $attempt, int $questionId, ?int $selectedOptionId, User $actingUser): OnlineTestAnswer
    {
        $this->autoSubmitIfExpired($attempt, $actingUser);

        throw_unless($attempt->fresh()->status === 'in_progress', ValidationException::withMessages(['attempt' => 'This attempt has already been submitted.']));

        return OnlineTestAnswer::query()->updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $questionId],
            ['selected_option_id' => $selectedOptionId]
        );
    }

    /**
     * Logs one integrity event (tab_hidden/window_blur/fullscreen_exit) and
     * immediately force-submits — this app's policy is zero-tolerance, not
     * warn-then-flag, so there's no separate "just log it" path. Submitting
     * through the exact same submitAttempt() as a normal Submit click means
     * whatever the student had answered still counts; nothing about a
     * violation forfeits already-earned marks.
     */
    public function recordViolationAndSubmit(OnlineTestAttempt $attempt, string $eventType, User $actingUser): OnlineTestAttempt
    {
        $attempt->events()->create(['event_type' => $eventType]);
        $attempt->increment('violation_count');

        if ($attempt->fresh()->status === 'in_progress') {
            return $this->submitAttempt($attempt, $actingUser, autoReason: 'violation');
        }

        return $attempt->fresh();
    }

    /**
     * The earliest of "this attempt's own duration ran out" and "the exam's
     * global window closed" — either one ends it. Null (no deadline at all)
     * only when the exam subject has neither duration_minutes nor
     * online_ends_at set, which existing data predating this feature may
     * still have.
     */
    private function attemptDeadline(OnlineTestAttempt $attempt): ?Carbon
    {
        $examSubject = $attempt->examSubject;

        return collect([
            $examSubject->duration_minutes ? $attempt->started_at->copy()->addMinutes($examSubject->duration_minutes) : null,
            $examSubject->online_ends_at,
        ])->filter()->min();
    }

    /**
     * The client-side countdown (TakeOnlineTestPage) is the primary
     * mechanism for a student still actively on the page, and the
     * scheduled autoSubmitExpired() sweep is the backstop for a closed
     * tab — but neither protects against a student who keeps calling this
     * API directly after their own time is up while the tab stays open.
     * This closes that gap the moment it's next relevant, on the very next
     * save, rather than waiting up to 5 minutes for the cron sweep.
     */
    private function autoSubmitIfExpired(OnlineTestAttempt $attempt, User $actingUser): void
    {
        if ($attempt->status !== 'in_progress') {
            return;
        }

        $deadline = $this->attemptDeadline($attempt);
        if ($deadline && now()->gt($deadline)) {
            $this->submitAttempt($attempt, $actingUser, autoReason: 'time_expired');
        }
    }

    /**
     * Grades every answer (MCQ/True-False are always auto-gradable — that's
     * the whole point of restricting question types to those), sums the
     * score, and immediately writes it into the exam's official ExamMark
     * row so results are instant. If the student re-attempts, the latest
     * submitted attempt's score wins — see the class docblock on
     * OnlineTestAttempt for why "latest" rather than "best of N".
     *
     * A wrong (answered-but-incorrect) response costs
     * question.negative_marks if set — never applied to a genuinely
     * unanswered question, which always awards exactly 0. Per-question
     * marks_awarded stays the real (possibly negative) value for the
     * review breakdown; only the summed attempt score is floored at 0
     * (confirmed default — a school's total never displays negative even
     * if wrong answers outweigh correct ones).
     */
    public function submitAttempt(OnlineTestAttempt $attempt, User $actingUser, ?string $autoReason = null): OnlineTestAttempt
    {
        throw_unless($attempt->status === 'in_progress', ValidationException::withMessages(['attempt' => 'This attempt has already been submitted.']));

        return DB::transaction(function () use ($attempt, $actingUser, $autoReason) {
            // Re-check under a row lock, not the pre-transaction in-memory
            // model — two near-simultaneous submit requests could otherwise
            // both pass the throw_unless() above before either had written
            // 'submitted', double-scoring the same attempt.
            $locked = OnlineTestAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
            throw_unless($locked->status === 'in_progress', ValidationException::withMessages(['attempt' => 'This attempt has already been submitted.']));

            $examSubject = $attempt->examSubject;
            $onlineQuestions = $examSubject->onlineTestQuestions()->with('question.options')->get();

            $totalScore = 0.0;
            $maxScore = 0.0;

            foreach ($onlineQuestions as $otq) {
                $question = $otq->question;
                $marks = $otq->effectiveMarks();
                $maxScore += $marks;

                $correctOption = $question->correctOption();
                $answer = OnlineTestAnswer::query()->firstOrNew(['attempt_id' => $attempt->id, 'question_id' => $question->id]);

                $isCorrect = $correctOption && $answer->selected_option_id !== null && $answer->selected_option_id === $correctOption->id;
                $isWrongButAnswered = ! $isCorrect && $answer->selected_option_id !== null;

                $answer->is_correct = $isCorrect;
                $answer->marks_awarded = match (true) {
                    $isCorrect => $marks,
                    $isWrongButAnswered => -($question->negative_marks ?? 0),
                    default => 0,
                };
                $answer->save();

                $totalScore += $answer->marks_awarded;
            }

            $totalScore = max($totalScore, 0.0);

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'score' => $totalScore,
                'max_score' => $maxScore,
                'auto_submit_reason' => $autoReason,
            ]);

            $this->syncExamMark($attempt, $totalScore, $actingUser);

            return $attempt->refresh();
        });
    }

    /**
     * Backstop for attempts nobody ever submitted — a closed tab, a lost
     * connection, a browser crash. TakeOnlineTestPage's own client-side
     * countdown is the primary mechanism for a student still on the page;
     * this catches everything that timer can't (see
     * AutoSubmitExpiredOnlineTestsCommand, run on a schedule). Scored via
     * the exact same submitAttempt() path a real submission takes — whatever
     * the student answered before disappearing still counts, blank
     * questions score 0/negative like any other unanswered question.
     *
     * Attributed to a School Admin account (there's no real acting human
     * for a scheduled sweep) — if none exists yet, this run is skipped
     * rather than crashing, logged so it doesn't fail silently.
     */
    public function autoSubmitExpired(): int
    {
        $systemActor = User::role('School Admin')->first();

        if (! $systemActor) {
            Log::warning('exams:auto-submit-expired skipped: no School Admin account to attribute submissions to.');

            return 0;
        }

        $expiredAttempts = OnlineTestAttempt::query()
            ->where('status', 'in_progress')
            ->whereHas('examSubject', fn ($q) => $q->whereNotNull('online_ends_at')->where('online_ends_at', '<', now()))
            ->get();

        $submitted = 0;

        foreach ($expiredAttempts as $attempt) {
            try {
                $this->submitAttempt($attempt, $systemActor, autoReason: 'time_expired');
                $submitted++;
            } catch (Throwable $e) {
                Log::error('exams:auto-submit-expired failed for one attempt — continuing with the rest.', [
                    'attempt_id' => $attempt->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $submitted;
    }

    private function syncExamMark(OnlineTestAttempt $attempt, float $score, User $actingUser): void
    {
        $examSubject = $attempt->examSubject;

        ExamMark::query()->updateOrCreate(
            ['exam_subject_id' => $examSubject->id, 'student_id' => $attempt->student_id],
            [
                'marks_obtained' => $score,
                'is_absent' => false,
                'remarks' => 'Auto-graded via online test.',
                'entered_by' => $actingUser->id,
            ]
        );
    }
}
