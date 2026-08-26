<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\OnlineTestAttempt;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class AutoSubmitExpiredOnlineTestsCommandTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_command_auto_submits_an_expired_in_progress_attempt_and_scores_it(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

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
            'max_marks' => 5, 'is_online' => true, 'max_attempts' => 1,
            'online_ends_at' => now()->subMinutes(10),
        ]);
        tenancy()->initialize($school);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Student');
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'user_id' => $studentUser->id,
        ]);

        tenancy()->initialize($school);
        $question = Question::factory()->create(['created_by' => $admin->id, 'type' => 'mcq', 'default_marks' => 5]);
        foreach (['A', 'B'] as $i => $label) {
            tenancy()->initialize($school);
            QuestionOption::factory()->create(['question_id' => $question->id, 'option_text' => $label, 'is_correct' => $i === 0, 'sequence' => $i]);
        }
        tenancy()->initialize($school);
        $examSubject->onlineTestQuestions()->create(['question_id' => $question->id, 'sequence' => 0]);

        tenancy()->initialize($school);
        $attempt = OnlineTestAttempt::query()->create([
            'exam_subject_id' => $examSubject->id, 'student_id' => $student->id,
            'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now()->subMinutes(20),
        ]);
        tenancy()->end();

        $this->artisan('exams:auto-submit-expired')->assertExitCode(0);

        $school->run(function () use ($attempt, $examSubject, $student) {
            $this->assertSame('submitted', $attempt->fresh()->status);
            // Never answered — auto-submit scores it 0, not a penalty.
            $this->assertDatabaseHas('exam_marks', [
                'exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 0,
            ]);
        });
    }

    /** Degrade-don't-crash: a tenant with no School Admin to attribute the sweep to is skipped, not fatal for the batch. */
    public function test_command_skips_a_tenant_with_no_school_admin_without_crashing(): void
    {
        $school = $this->createSchool(); // no School Admin created for this tenant

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
            'max_marks' => 5, 'is_online' => true, 'max_attempts' => 1,
            'online_ends_at' => now()->subMinutes(10),
        ]);
        tenancy()->initialize($school);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Student');
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            'user_id' => $studentUser->id,
        ]);
        tenancy()->initialize($school);
        $attempt = OnlineTestAttempt::query()->create([
            'exam_subject_id' => $examSubject->id, 'student_id' => $student->id,
            'attempt_number' => 1, 'status' => 'in_progress', 'started_at' => now()->subMinutes(20),
        ]);
        tenancy()->end();

        $this->artisan('exams:auto-submit-expired')->assertExitCode(0);

        $school->run(function () use ($attempt) {
            $this->assertSame('in_progress', $attempt->fresh()->status);
        });
    }
}
