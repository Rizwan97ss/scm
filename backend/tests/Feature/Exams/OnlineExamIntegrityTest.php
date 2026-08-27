<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\OnlineTestAttempt;
use App\Models\Question;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * The join window (early_access_minutes / late_join_grace_minutes), the
 * per-attempt deadline enforced on save (not just at start), and zero-
 * tolerance integrity violations (tab switch / window blur / fullscreen
 * exit auto-submitting immediately) — added together since they're all
 * part of the same "no one can bypass the exam clock" guarantee.
 */
class OnlineExamIntegrityTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeOnlineExamSubject(School $school, array $attrs = []): array
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();
        tenancy()->initialize($school);
        $exam = Exam::factory()->create(['academic_year_id' => $year->id]);
        tenancy()->initialize($school);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'max_marks' => 10, 'is_online' => true, 'max_attempts' => 1, ...$attrs,
        ]);

        tenancy()->initialize($school);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Student');
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'user_id' => $studentUser->id,
        ]);

        return [$examSubject, $student, $studentUser];
    }

    public function test_student_can_start_within_the_early_access_window(): void
    {
        $startsAt = Carbon::parse('2026-09-01 10:00:00');
        $this->travelTo($startsAt->copy()->subMinutes(3));

        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school, [
            'online_starts_at' => $startsAt, 'early_access_minutes' => 5, 'late_join_grace_minutes' => 2,
        ]);

        $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->assertOk();
    }

    public function test_student_cannot_start_before_the_early_access_window_opens(): void
    {
        $startsAt = Carbon::parse('2026-09-01 10:00:00');
        $this->travelTo($startsAt->copy()->subMinutes(10));

        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school, [
            'online_starts_at' => $startsAt, 'early_access_minutes' => 5,
        ]);

        $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This test has not opened yet.']);
    }

    public function test_student_cannot_start_after_the_late_join_cutoff(): void
    {
        $startsAt = Carbon::parse('2026-09-01 10:00:00');
        $this->travelTo($startsAt->copy()->addMinutes(5));

        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school, [
            'online_starts_at' => $startsAt, 'late_join_grace_minutes' => 2,
        ]);

        $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This test has already started and can no longer be joined.']);
    }

    public function test_a_student_who_started_on_time_can_still_resume_after_the_late_join_cutoff_passes(): void
    {
        $startsAt = Carbon::parse('2026-09-01 10:00:00');
        $this->travelTo($startsAt);

        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school, [
            'online_starts_at' => $startsAt, 'late_join_grace_minutes' => 2, 'duration_minutes' => 60,
        ]);

        $firstAttemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertOk()->json('data.attempt.id');

        // Now well past the join cutoff, but still well inside the 60-minute duration.
        $this->travelTo($startsAt->copy()->addMinutes(10));

        $resumed = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")->assertOk();
        $this->assertSame($firstAttemptId, $resumed->json('data.attempt.id'));
    }

    public function test_saving_an_answer_past_the_attempts_own_deadline_auto_submits_it(): void
    {
        $startsAt = Carbon::parse('2026-09-01 10:00:00');
        $this->travelTo($startsAt);

        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school, [
            'online_starts_at' => $startsAt, 'duration_minutes' => 30,
        ]);

        $attemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertOk()->json('data.attempt.id');

        tenancy()->initialize($school);
        $question = Question::factory()->create(['type' => 'mcq']);

        // Past the 30-minute duration, even though online_ends_at (if any) hasn't closed.
        $this->travelTo($startsAt->copy()->addMinutes(31));

        $this->actingAsInSchool($studentUser)->putJson("/api/v1/online-test-attempts/{$attemptId}/answers", ['question_id' => $question->id])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'This attempt has already been submitted.']);

        tenancy()->initialize($school);
        $attempt = OnlineTestAttempt::query()->findOrFail($attemptId);
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('time_expired', $attempt->auto_submit_reason);
    }

    public function test_reporting_a_violation_immediately_submits_the_attempt(): void
    {
        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school);

        $attemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertOk()->json('data.attempt.id');

        $response = $this->actingAsInSchool($studentUser)
            ->postJson("/api/v1/online-test-attempts/{$attemptId}/violations", ['event_type' => 'tab_hidden']);

        $response->assertOk()->assertJsonPath('data.status', 'submitted');

        tenancy()->initialize($school);
        $attempt = OnlineTestAttempt::query()->with('events')->findOrFail($attemptId);
        $this->assertSame('submitted', $attempt->status);
        $this->assertSame('violation', $attempt->auto_submit_reason);
        $this->assertSame(1, $attempt->violation_count);
        $this->assertCount(1, $attempt->events);
        $this->assertSame('tab_hidden', $attempt->events->first()->event_type);
    }

    public function test_reporting_a_violation_after_the_attempt_was_already_submitted_does_not_error(): void
    {
        $school = $this->createSchool();
        [$examSubject, , $studentUser] = $this->makeOnlineExamSubject($school);

        $attemptId = $this->actingAsInSchool($studentUser)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertOk()->json('data.attempt.id');
        $this->actingAsInSchool($studentUser)->postJson("/api/v1/online-test-attempts/{$attemptId}/submit")->assertOk();

        $this->actingAsInSchool($studentUser)
            ->postJson("/api/v1/online-test-attempts/{$attemptId}/violations", ['event_type' => 'window_blur'])
            ->assertOk();

        // The already-submitted attempt keeps its normal (non-auto) reason —
        // a violation reported too late doesn't retroactively relabel it.
        tenancy()->initialize($school);
        $attempt = OnlineTestAttempt::query()->findOrFail($attemptId);
        $this->assertNull($attempt->auto_submit_reason);
    }

    public function test_a_student_cannot_report_a_violation_on_another_students_attempt(): void
    {
        $school = $this->createSchool();
        [$examSubject, , $studentUserA] = $this->makeOnlineExamSubject($school);
        tenancy()->initialize($school);
        $studentUserB = User::factory()->create();
        $studentUserB->assignRole('Student');

        $attemptId = $this->actingAsInSchool($studentUserA)->postJson("/api/v1/exam-subjects/{$examSubject->id}/attempts")
            ->assertOk()->json('data.attempt.id');

        $this->actingAsInSchool($studentUserB)
            ->postJson("/api/v1/online-test-attempts/{$attemptId}/violations", ['event_type' => 'tab_hidden'])
            ->assertStatus(403);
    }
}
