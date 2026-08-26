<?php

namespace Tests\Feature\Homework;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class HomeworkTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function makeSectionAndSubject(School $school, ?int $teacherId = null): array
    {
        tenancy()->initialize($school);
        $year = AcademicYear::factory()->create();
        tenancy()->initialize($school);
        $gradeLevel = GradeLevel::factory()->create();
        tenancy()->initialize($school);
        $section = Section::factory()->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
        tenancy()->initialize($school);
        $subject = Subject::factory()->create();

        if ($teacherId) {
            ClassSubjectTeacher::query()->create([
                'academic_year_id' => $year->id,
                'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherId,
            ]);
        }

        tenancy()->initialize($school);
        $student = Student::factory()->create([
            'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
        ]);

        return [$year, $section, $subject, $student];
    }

    public function test_teacher_can_create_homework_for_a_subject_they_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$year, $section, $subject] = $this->makeSectionAndSubject($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/homework', [
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'title' => 'Chapter 5 exercises',
            'due_date' => now()->addWeek()->toDateString(),
            'max_score' => 20,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('homeworks', ['title' => 'Chapter 5 exercises', 'teacher_id' => $teacher->id]);
    }

    public function test_teacher_cannot_create_homework_for_a_subject_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$year, $section, $subject] = $this->makeSectionAndSubject($school); // no teacher assigned

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/homework', [
            'academic_year_id' => $year->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'title' => 'Chapter 5 exercises',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_student_only_sees_homework_for_their_own_section(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$year, $section, $subject, $student] = $this->makeSectionAndSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        [, $otherSection, $otherSubject] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $otherSection->id, 'subject_id' => $otherSubject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAsInSchool($studentUser)->getJson('/api/v1/homework?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_student_can_submit_homework_and_resubmitting_updates_instead_of_duplicating(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$year, $section, $subject, $student] = $this->makeSectionAndSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $homework = Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $submit = fn (string $content) => $this->actingAsInSchool($studentUser)->postJson("/api/v1/homework/{$homework->id}/submit", ['content' => $content]);

        $submit('first draft')->assertOk();
        $submit('final answer')->assertOk();

        $this->assertSame(1, HomeworkSubmission::query()->where('homework_id', $homework->id)->where('student_id', $student->id)->count());
        $this->assertDatabaseHas('homework_submissions', ['homework_id' => $homework->id, 'student_id' => $student->id, 'content' => 'final answer']);
    }

    public function test_student_cannot_submit_homework_outside_their_section(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [$year, , , $student] = $this->makeSectionAndSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $otherSection, $otherSubject] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        $homework = Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $otherSection->id, 'subject_id' => $otherSubject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAsInSchool($studentUser)->postJson("/api/v1/homework/{$homework->id}/submit", ['content' => 'sneaky']);

        $response->assertStatus(403);
    }

    public function test_teacher_can_grade_a_submission_for_a_subject_they_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$year, $section, $subject, $student] = $this->makeSectionAndSubject($school, $teacher->id);

        tenancy()->initialize($school);
        $homework = Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
        tenancy()->initialize($school);
        $submission = HomeworkSubmission::factory()->create(['homework_id' => $homework->id, 'student_id' => $student->id]);

        $response = $this->actingAsInSchool($teacher)->putJson("/api/v1/homework-submissions/{$submission->id}/grade", [
            'score' => 18, 'feedback' => 'Great work!',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'graded');
        $this->assertDatabaseHas('homework_submissions', ['id' => $submission->id, 'score' => 18, 'status' => 'graded']);
    }

    public function test_teacher_cannot_grade_a_submission_for_a_subject_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [$year, $section, $subject, $student] = $this->makeSectionAndSubject($school); // no teacher assigned

        $otherTeacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $homework = Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id,
        ]);
        tenancy()->initialize($school);
        $submission = HomeworkSubmission::factory()->create(['homework_id' => $homework->id, 'student_id' => $student->id]);

        $response = $this->actingAsInSchool($teacher)->putJson("/api/v1/homework-submissions/{$submission->id}/grade", ['score' => 18]);

        $response->assertStatus(403);
    }

    public function test_parent_can_view_childs_homework(): void
    {
        $school = $this->createSchool();
        $parentUser = $this->createUserWithRole($school, 'Parent');
        [$year, $section, $subject, $student] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        Homework::factory()->create([
            'academic_year_id' => $year->id, 'section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAsInSchool($parentUser)->getJson("/api/v1/parent/children/{$student->id}/homework");

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
