<?php

namespace Tests\Feature\Exams;

use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeLevel;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * The exam timetable (date + start/end time per subject, one section at a
 * time): reading it is open to any exam-timetable.view holder (or a
 * Student/Parent viewing their own section via ?student_id=), but writing
 * it is restricted to Admin/Principal or that section's own class teacher —
 * same "narrow, section-scoped edit right" shape as
 * ExamSubjectGroupResultTest's publish/unpublish tests, just for scheduling
 * instead of results.
 */
class ExamTimetableTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    /** @return array{0: Exam, 1: ExamSubject, 2: Section, 3: Student} */
    private function makeExamWithSubject(School $school, ?int $classTeacherId = null): array
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'class_teacher_id' => $classTeacherId,
        ]);
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();
        tenancy()->initialize($school);
        $exam = Exam::factory()->create(['academic_year_id' => $year->id]);
        tenancy()->initialize($school);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id, 'subject_id' => $subject->id, 'section_id' => $section->id, 'max_marks' => 100,
        ]);
        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
        ]);

        return [$exam, $examSubject, $section, $student];
    }

    public function test_admin_can_view_and_edit_any_sections_timetable(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $examSubject, $section] = $this->makeExamWithSubject($school);

        $response = $this->actingAsInSchool($admin)->putJson("/api/v1/exams/{$exam->id}/timetable", [
            'section_id' => $section->id,
            'items' => [['exam_subject_id' => $examSubject->id, 'exam_date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '12:00']],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.rows.0.exam_date', '2026-09-10')
            ->assertJsonPath('data.rows.0.start_time', '09:00:00')
            ->assertJsonPath('data.rows.0.end_time', '12:00:00');
    }

    public function test_class_teacher_can_edit_their_own_sections_timetable(): void
    {
        $school = $this->createSchool();
        $classTeacher = $this->createUserWithRole($school, 'Class Teacher');
        [$exam, $examSubject, $section] = $this->makeExamWithSubject($school, classTeacherId: $classTeacher->id);

        $response = $this->actingAsInSchool($classTeacher)->putJson("/api/v1/exams/{$exam->id}/timetable", [
            'section_id' => $section->id,
            'items' => [['exam_subject_id' => $examSubject->id, 'exam_date' => '2026-09-10', 'start_time' => '09:00', 'end_time' => '12:00']],
        ]);

        $response->assertOk()->assertJsonPath('data.rows.0.start_time', '09:00:00');
    }

    public function test_class_teacher_cannot_edit_a_section_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $classTeacher = $this->createUserWithRole($school, 'Class Teacher');
        [$exam, $examSubject, $section] = $this->makeExamWithSubject($school);

        $response = $this->actingAsInSchool($classTeacher)->putJson("/api/v1/exams/{$exam->id}/timetable", [
            'section_id' => $section->id,
            'items' => [['exam_subject_id' => $examSubject->id, 'exam_date' => '2026-09-10']],
        ]);

        $response->assertStatus(403);
    }

    public function test_plain_teacher_can_view_but_not_edit_the_timetable(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$exam, , $section] = $this->makeExamWithSubject($school, classTeacherId: $teacher->id);

        $this->actingAsInSchool($teacher)->getJson("/api/v1/exams/{$exam->id}/timetable?section_id={$section->id}")
            ->assertOk()
            ->assertJsonPath('data.can_edit', false);

        $this->actingAsInSchool($teacher)->putJson("/api/v1/exams/{$exam->id}/timetable", ['section_id' => $section->id, 'items' => []])
            ->assertStatus(403);
    }

    public function test_a_student_can_view_their_own_sections_timetable(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$exam, $examSubject, , $student] = $this->makeExamWithSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        tenancy()->initialize($school);
        $examSubject->update(['exam_date' => '2026-09-10', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        $response = $this->actingAsInSchool($studentUser)->getJson("/api/v1/exams/{$exam->id}/timetable?student_id={$student->id}");

        $response->assertOk()->assertJsonPath('data.rows.0.subject_name', $examSubject->fresh()->subject->name);
    }

    public function test_a_student_cannot_view_another_students_timetable(): void
    {
        $school = $this->createSchool();
        $studentUserA = $this->createUserWithRole($school, 'Student');
        [$exam, , , $studentB] = $this->makeExamWithSubject($school);

        $response = $this->actingAsInSchool($studentUserA)->getJson("/api/v1/exams/{$exam->id}/timetable?student_id={$studentB->id}");

        $response->assertStatus(403);
    }

    public function test_updating_rejects_an_exam_subject_from_a_different_section(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, , $section] = $this->makeExamWithSubject($school);
        [, $otherExamSubject] = $this->makeExamWithSubject($school);

        $response = $this->actingAsInSchool($admin)->putJson("/api/v1/exams/{$exam->id}/timetable", [
            'section_id' => $section->id,
            'items' => [['exam_subject_id' => $otherExamSubject->id, 'exam_date' => '2026-09-10']],
        ]);

        $response->assertStatus(422);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        [$exam, $examSubject, $section] = $this->makeExamWithSubject($school);

        $response = $this->actingAsInSchool($admin)->putJson("/api/v1/exams/{$exam->id}/timetable", [
            'section_id' => $section->id,
            'items' => [['exam_subject_id' => $examSubject->id, 'start_time' => '12:00', 'end_time' => '09:00']],
        ]);

        $response->assertStatus(422);
    }
}
