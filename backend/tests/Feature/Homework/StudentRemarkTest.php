<?php

namespace Tests\Feature\Homework;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentRemark;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class StudentRemarkTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeStudentInSection(School $school, ?int $teacherId = null): array
    {
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        if ($teacherId) {
            $subject = Subject::factory()->for($school)->create();
            ClassSubjectTeacher::query()->create([
                'school_id' => $school->id, 'academic_year_id' => $year->id,
                'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherId,
            ]);
        }

        $student = Student::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
        ]);

        return [$section, $student];
    }

    public function test_teacher_can_add_a_remark_for_a_student_in_a_section_they_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $student] = $this->makeStudentInSection($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/student-remarks', [
            'student_id' => $student->id,
            'category' => 'behavioral',
            'body' => 'Excellent participation in class today.',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('student_remarks', ['student_id' => $student->id, 'author_id' => $teacher->id, 'category' => 'behavioral']);
    }

    public function test_teacher_cannot_add_a_remark_for_a_student_outside_their_section(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $student] = $this->makeStudentInSection($school); // no teacher assigned

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/student-remarks', [
            'student_id' => $student->id,
            'body' => 'Should not be allowed.',
        ]);

        $response->assertStatus(403);
    }

    public function test_student_can_view_remarks_about_themselves(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $student] = $this->makeStudentInSection($school);
        $student->update(['user_id' => $studentUser->id]);

        StudentRemark::factory()->for($school)->create(['student_id' => $student->id, 'author_id' => $teacher->id]);

        $response = $this->actingAsInSchool($studentUser)->getJson('/api/v1/student-remarks?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_parent_only_sees_remarks_marked_visible_to_guardian(): void
    {
        $school = $this->createSchool();
        $parentUser = $this->createUserWithRole($school, 'Parent');
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $student] = $this->makeStudentInSection($school);
        $guardian = Guardian::factory()->for($school)->create(['user_id' => $parentUser->id]);
        $guardian->students()->attach($student->id, ['relationship_type' => 'father', 'is_primary' => true]);

        StudentRemark::factory()->for($school)->create(['student_id' => $student->id, 'author_id' => $teacher->id, 'visible_to_guardian' => true]);
        StudentRemark::factory()->for($school)->create(['student_id' => $student->id, 'author_id' => $teacher->id, 'visible_to_guardian' => false]);

        $response = $this->actingAsInSchool($parentUser)->getJson("/api/v1/parent/children/{$student->id}/remarks");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_school_admin_can_filter_remarks_by_student(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $studentA] = $this->makeStudentInSection($school);
        [, $studentB] = $this->makeStudentInSection($school);

        StudentRemark::factory()->for($school)->create(['student_id' => $studentA->id, 'author_id' => $teacher->id]);
        StudentRemark::factory()->for($school)->create(['student_id' => $studentB->id, 'author_id' => $teacher->id]);

        $response = $this->actingAsInSchool($admin)->getJson("/api/v1/student-remarks?filter[student_id]={$studentA->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
