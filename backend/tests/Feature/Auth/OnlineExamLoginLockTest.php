<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\OnlineTestAttempt;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * The exam-integrity login lock: a student with a genuinely live
 * in-progress attempt (a recent heartbeat) can't be logged into from
 * anywhere else — see OnlineExamService::hasActiveExamLock(). Liveness,
 * not mere existence, is what's checked, so a dropped connection lets the
 * legitimate student back in on their own within LOCK_LIVENESS_SECONDS
 * rather than needing anyone to intervene.
 */
class OnlineExamLoginLockTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeStudentWithAttempt(School $school, string $lastSeenAgo): User
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
            'max_marks' => 10, 'is_online' => true,
        ]);

        tenancy()->initialize($school);
        $studentUser = User::factory()->create([
            'email' => 'locked-student@example.com', 'password' => Hash::make('correct-password'),
        ]);
        $studentUser->assignRole('Student');
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'user_id' => $studentUser->id,
        ]);

        tenancy()->initialize($school);
        OnlineTestAttempt::query()->create([
            'exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'attempt_number' => 1,
            'status' => 'in_progress', 'started_at' => now(), 'last_seen_at' => now()->sub($lastSeenAgo),
        ]);

        return $studentUser;
    }

    public function test_login_is_rejected_while_the_students_attempt_has_a_fresh_heartbeat(): void
    {
        $school = $this->createSchool();
        $this->makeStudentWithAttempt($school, '5 seconds');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked-student@example.com', 'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('in progress on another device', $response->json('errors.email.0'));
        $this->assertGuest();
    }

    public function test_login_is_allowed_once_the_heartbeat_has_gone_stale(): void
    {
        $school = $this->createSchool();
        $user = $this->makeStudentWithAttempt($school, '60 seconds');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked-student@example.com', 'password' => 'correct-password',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_login_lock_does_not_apply_to_a_student_with_no_in_progress_attempt(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Student', [
            'email' => 'free-student@example.com', 'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'free-student@example.com', 'password' => 'correct-password',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_heartbeat_endpoint_refreshes_last_seen_at_and_keeps_the_lock_alive(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->makeStudentWithAttempt($school, '40 seconds');

        tenancy()->initialize($school);
        $attempt = OnlineTestAttempt::query()->where('student_id', function ($q) use ($studentUser) {
            $q->select('id')->from('students')->where('user_id', $studentUser->id);
        })->first();

        $this->actingAsInSchool($studentUser)
            ->postJson("/api/v1/online-test-attempts/{$attempt->id}/heartbeat")
            ->assertOk();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked-student@example.com', 'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('in progress on another device', $response->json('errors.email.0'));
    }
}
