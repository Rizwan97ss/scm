<?php

namespace Tests\Feature\CourseMaterials;

use App\Models\AcademicYear;
use App\Models\ClassSubjectTeacher;
use App\Models\CourseMaterial;
use App\Models\CourseMaterialProgress;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class CourseMaterialTest extends TestCase
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

    public function test_teacher_can_create_material_for_a_subject_they_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $section, $subject] = $this->makeSectionAndSubject($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/course-materials', [
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'title' => 'Photosynthesis Notes',
            'type' => 'document',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('course_materials', ['title' => 'Photosynthesis Notes', 'teacher_id' => $teacher->id]);
    }

    public function test_teacher_cannot_create_material_for_a_subject_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $section, $subject] = $this->makeSectionAndSubject($school); // no teacher assigned

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/course-materials', [
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'title' => 'Photosynthesis Notes',
            'type' => 'document',
        ]);

        $response->assertStatus(403);
    }

    public function test_a_link_material_requires_a_url(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $section, $subject] = $this->makeSectionAndSubject($school, $teacher->id);

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/course-materials', [
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'title' => 'Khan Academy Video',
            'type' => 'video',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('url');
    }

    public function test_student_only_sees_published_material_for_their_own_section(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [, $section, $subject, $student] = $this->makeSectionAndSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        CourseMaterial::factory()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'is_published' => true]);
        tenancy()->initialize($school);
        CourseMaterial::factory()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'is_published' => false]);

        [, $otherSection, $otherSubject] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        CourseMaterial::factory()->create(['section_id' => $otherSection->id, 'subject_id' => $otherSubject->id, 'teacher_id' => $teacher->id]);

        $response = $this->actingAsInSchool($studentUser)->getJson('/api/v1/course-materials?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_student_can_mark_progress_and_marking_twice_updates_instead_of_duplicating(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [, $section, $subject, $student] = $this->makeSectionAndSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $material = CourseMaterial::factory()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $this->actingAsInSchool($studentUser)->postJson("/api/v1/course-materials/{$material->id}/progress")->assertOk();
        $response = $this->actingAsInSchool($studentUser)->postJson("/api/v1/course-materials/{$material->id}/progress", ['completed' => true]);

        $response->assertOk()->assertJsonPath('data.completed_at', fn ($value) => $value !== null);
        $this->assertSame(1, CourseMaterialProgress::query()->where('course_material_id', $material->id)->where('student_id', $student->id)->count());
    }

    public function test_student_cannot_mark_progress_on_material_outside_their_section(): void
    {
        $school = $this->createSchool();
        $studentUser = $this->createUserWithRole($school, 'Student');
        [, , , $student] = $this->makeSectionAndSubject($school);
        $student->update(['user_id' => $studentUser->id]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $otherSection, $otherSubject] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        $material = CourseMaterial::factory()->create(['section_id' => $otherSection->id, 'subject_id' => $otherSubject->id, 'teacher_id' => $teacher->id]);

        $response = $this->actingAsInSchool($studentUser)->postJson("/api/v1/course-materials/{$material->id}/progress");

        $response->assertStatus(403);
    }

    public function test_parent_can_view_childs_section_material(): void
    {
        $school = $this->createSchool();
        $parentUser = $this->createUserWithRole($school, 'Parent');
        [, $section, $subject, $student] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        $guardian = Guardian::factory()->create(['user_id' => $parentUser->id]);
        $guardian->students()->attach($student->id, ['relationship_type' => 'mother', 'is_primary' => true]);

        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        CourseMaterial::factory()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $response = $this->actingAsInSchool($parentUser)->getJson('/api/v1/course-materials?per_page=50');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_school_admin_can_delete_any_material(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $teacher = $this->createUserWithRole($school, 'Teacher');
        [, $section, $subject] = $this->makeSectionAndSubject($school);
        tenancy()->initialize($school);
        $material = CourseMaterial::factory()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $response = $this->actingAsInSchool($admin)->deleteJson("/api/v1/course-materials/{$material->id}");

        $response->assertOk();
        $this->assertSoftDeleted('course_materials', ['id' => $material->id]);
    }
}
