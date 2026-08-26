<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\OnlineTestAnswer;
use App\Models\OnlineTestAttempt;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OnlineExamService
{
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
     */
    public function startAttempt(ExamSubject $examSubject, Student $student): OnlineTestAttempt
    {
        throw_unless($examSubject->is_online, ValidationException::withMessages(['exam_subject' => 'This subject is not configured for an online test.']));
        throw_unless($student->current_section_id === $examSubject->section_id, ValidationException::withMessages(['student' => 'You are not enrolled in this section.']));

        $now = now();
        if ($examSubject->online_starts_at && $now->lt($examSubject->online_starts_at)) {
            throw ValidationException::withMessages(['window' => 'This test has not opened yet.']);
        }
        if ($examSubject->online_ends_at && $now->gt($examSubject->online_ends_at)) {
            throw ValidationException::withMessages(['window' => 'This test has closed.']);
        }

        $existingAttempts = OnlineTestAttempt::query()
            ->where('exam_subject_id', $examSubject->id)
            ->where('student_id', $student->id)
            ->count();

        // Resuming an already-started attempt must never be blocked by the
        // attempt cap — the cap only limits starting a genuinely NEW attempt.
        // Checking it before this would make a student who has one in-progress
        // attempt at max_attempts=1 unable to resume it after e.g. a page
        // reload, which defeats the point of having a resumable attempt.
        $inProgress = OnlineTestAttempt::query()
            ->where('exam_subject_id', $examSubject->id)
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();

        if ($inProgress) {
            return $inProgress;
        }

        throw_if($existingAttempts >= $examSubject->max_attempts, ValidationException::withMessages(['attempts' => 'No attempts remaining.']));

        return OnlineTestAttempt::query()->create([
            'exam_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'attempt_number' => $existingAttempts + 1,
            'status' => 'in_progress',
            'started_at' => $now,
        ]);
    }

    public function saveAnswer(OnlineTestAttempt $attempt, int $questionId, ?int $selectedOptionId): OnlineTestAnswer
    {
        throw_unless($attempt->status === 'in_progress', ValidationException::withMessages(['attempt' => 'This attempt has already been submitted.']));

        return OnlineTestAnswer::query()->updateOrCreate(
            ['attempt_id' => $attempt->id, 'question_id' => $questionId],
            ['selected_option_id' => $selectedOptionId]
        );
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
    public function submitAttempt(OnlineTestAttempt $attempt, User $actingUser): OnlineTestAttempt
    {
        throw_unless($attempt->status === 'in_progress', ValidationException::withMessages(['attempt' => 'This attempt has already been submitted.']));

        return DB::transaction(function () use ($attempt, $actingUser) {
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
     * Attributed to the school's own admin (there's no real acting human
     * for a scheduled sweep) — a tenant with zero School Admin accounts is
     * skipped for this run rather than crashing the whole batch, logged so
     * it doesn't fail silently.
     */
    public function autoSubmitExpired(): int
    {
        $systemActor = User::role('School Admin')->first();

        if (! $systemActor) {
            Log::warning('exams:auto-submit-expired skipped a tenant with no School Admin account to attribute submissions to.', ['tenant' => tenant()?->id]);

            return 0;
        }

        $expiredAttempts = OnlineTestAttempt::query()
            ->where('status', 'in_progress')
            ->whereHas('examSubject', fn ($q) => $q->whereNotNull('online_ends_at')->where('online_ends_at', '<', now()))
            ->get();

        $submitted = 0;

        foreach ($expiredAttempts as $attempt) {
            try {
                $this->submitAttempt($attempt, $systemActor);
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
