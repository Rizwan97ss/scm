<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamSubject;
use App\Models\GradeBand;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class ExamTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeExamSubject(School $school, ?int $teacherId = null): array
    {
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        $subject = Subject::factory()->for($school)->create();
        $exam = Exam::factory()->for($school)->create(['academic_year_id' => $year->id]);
        $examSubject = ExamSubject::factory()->for($school)->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => 100,
        ]);

        if ($teacherId) {
            ClassSubjectTeacher::query()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id,
                'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherId,
            ]);
        }

        $student = Student::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
        ]);

        return [$exam, $examSubject, $student, $section, $subject, $year];
    }

    public function test_admin_can_create_an_exam_with_exam_subjects(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        $subject = Subject::factory()->for($school)->create();

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/exams', [
            'academic_year_id' => $year->id,
            'name' => 'Midterm Exam',
            'exam_subjects' => [
                ['subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => 100, 'passing_marks' => 40],
            ],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.exam_subjects');
        $this->assertDatabaseHas('exams', ['school_id' => $school->id, 'name' => 'Midterm Exam']);
    }

    public function test_updating_an_exam_preserves_marks_on_unchanged_exam_subjects(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $examSubject, $student] = $this->makeExamSubject($school);

        ExamMark::factory()->for($school)->create([
            'exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 88, 'entered_by' => $admin->id,
        ]);

        $response = $this->actingAsInSchool($admin)->putJson("/api/v1/exams/{$exam->id}", [
            'academic_year_id' => $exam->academic_year_id,
            'name' => 'Midterm Exam (renamed)',
            'exam_subjects' => [
                ['subject_id' => $examSubject->subject_id, 'section_id' => $examSubject->section_id, 'max_marks' => 100],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 88]);
        $this->assertSame(1, ExamSubject::query()->where('exam_id', $exam->id)->count());
    }

    public function test_teacher_can_enter_marks_for_a_subject_they_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $examSubject, $student] = $this->makeExamSubject($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/exam-subjects/{$examSubject->id}/marks", [
            'entries' => [['student_id' => $student->id, 'marks_obtained' => 78]],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 78]);
    }

    public function test_teacher_cannot_enter_marks_for_a_subject_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $examSubject, $student] = $this->makeExamSubject($school); // no teacher assigned

        $response = $this->actingAsInSchool($teacher)->postJson("/api/v1/exam-subjects/{$examSubject->id}/marks", [
            'entries' => [['student_id' => $student->id, 'marks_obtained' => 78]],
        ]);

        $response->assertStatus(403);
    }

    public function test_marks_cannot_exceed_the_subjects_max_marks(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [, $examSubject, $student] = $this->makeExamSubject($school);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/exam-subjects/{$examSubject->id}/marks", [
            'entries' => [['student_id' => $student->id, 'marks_obtained' => 150]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('entries.0.marks_obtained');
    }

    public function test_resubmitting_marks_updates_instead_of_duplicating(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [, $examSubject, $student] = $this->makeExamSubject($school);

        $mark = fn (int $marks) => $this->actingAsInSchool($admin)->postJson("/api/v1/exam-subjects/{$examSubject->id}/marks", [
            'entries' => [['student_id' => $student->id, 'marks_obtained' => $marks]],
        ]);

        $mark(70)->assertOk();
        $mark(85)->assertOk();

        $this->assertSame(1, ExamMark::query()->where('exam_subject_id', $examSubject->id)->where('student_id', $student->id)->count());
        $this->assertDatabaseHas('exam_marks', ['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 85]);
    }

    public function test_report_card_computes_grade_and_percentage_from_the_default_scale(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $examSubject, $student] = $this->makeExamSubject($school);

        $scale = GradingScale::factory()->for($school)->create(['is_default' => true]);
        GradeBand::factory()->for($school)->create(['grading_scale_id' => $scale->id, 'min_percentage' => 80, 'max_percentage' => 100, 'grade_label' => 'A', 'grade_point' => 4.0]);
        GradeBand::factory()->for($school)->create(['grading_scale_id' => $scale->id, 'min_percentage' => 0, 'max_percentage' => 79.99, 'grade_label' => 'B', 'grade_point' => 3.0]);

        ExamMark::factory()->for($school)->create(['exam_subject_id' => $examSubject->id, 'student_id' => $student->id, 'marks_obtained' => 85, 'entered_by' => $admin->id]);

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/exams/{$exam->id}/report-card?student_id={$student->id}");

        // json_encode emits a whole-number float like 85.0 without the decimal
        // point (PHP's serialize_precision quirk), so it round-trips through
        // json_decode as an int — compare against 85/4, not 85.0/4.0.
        $response->assertOk()
            ->assertJsonPath('data.subjects.0.percentage', 85)
            ->assertJsonPath('data.subjects.0.grade_label', 'A')
            ->assertJsonPath('data.overall_percentage', 85)
            ->assertJsonPath('data.overall_gpa', 4);
    }

    public function test_student_cannot_view_report_card_before_exam_is_published(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$exam, $examSubject, $student] = $this->makeExamSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/exams/{$exam->id}/report-card?student_id={$student->id}");

        $response->assertStatus(403);
    }

    public function test_student_can_view_report_card_once_published(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$exam, $examSubject, $student] = $this->makeExamSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$exam->id}/publish")->assertOk();

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/exams/{$exam->id}/report-card?student_id={$student->id}");

        $response->assertOk();
    }

    public function test_student_cannot_list_unpublished_or_unrelated_exams(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$exam, $examSubject, $student] = $this->makeExamSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        // Unrelated published exam (different section) — should still be hidden.
        [$otherExam] = $this->makeExamSubject($school);
        $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$otherExam->id}/publish")->assertOk();

        $response = $this->actingAsInSchool($studentUser)->getJson('/api/v1/exams?per_page=50');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($exam->id)); // not yet published
        $this->assertFalse($ids->contains($otherExam->id)); // published but unrelated section
    }

    public function test_parent_can_view_published_report_card_for_their_child(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $parentUser = $this->createUserWithRole($school, 'Parent');
        $guardian = Guardian::factory()->for($school)->create(['user_id' => $parentUser->id]);
        [$exam, $examSubject, $student] = $this->makeExamSubject($school);
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        $this->actingAsInSchool($admin)->postJson("/api/v1/exams/{$exam->id}/publish")->assertOk();

        $response = $this->actingAsInSchool($parentUser)->getJson("/api/v1/parent/children/{$student->id}/report-card?exam_id={$exam->id}");

        $response->assertOk();
    }

    public function test_school_admin_cannot_access_another_schools_exam(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $adminA = $this->createUserWithRole($schoolA, 'School Admin');
        [$examB] = $this->makeExamSubject($schoolB);

        $response = $this->actingAsInSchool($adminA)->getJson("/api/v1/exams/{$examB->id}");

        $response->assertStatus(404);
    }
}
