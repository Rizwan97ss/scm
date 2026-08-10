<?php

namespace Tests\Feature\Authorization;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class RoleScopedAccessTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_cannot_list_another_schools_students(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $adminA = $this->createUserWithRole($schoolA, 'School Admin');

        $yearB = AcademicYear::factory()->for($schoolB)->create();
        $gradeLevelB = GradeLevel::factory()->for($schoolB)->create();
        $sectionB = Section::factory()->for($schoolB)->create(['academic_year_id' => $yearB->id, 'grade_level_id' => $gradeLevelB->id]);
        Student::factory()->for($schoolB)->create([
            'academic_year_id' => $yearB->id, 'current_grade_level_id' => $gradeLevelB->id, 'current_section_id' => $sectionB->id,
            'first_name' => 'FromSchoolB',
        ]);

        $response = $this->actingAsInSchool($adminA)->getJson('/api/v1/students?per_page=50');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name');
        $this->assertFalse($names->contains('FromSchoolB'));
    }

    public function test_school_admin_cannot_view_another_schools_student_directly(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $adminA = $this->createUserWithRole($schoolA, 'School Admin');

        $yearB = AcademicYear::factory()->for($schoolB)->create();
        $gradeLevelB = GradeLevel::factory()->for($schoolB)->create();
        $sectionB = Section::factory()->for($schoolB)->create(['academic_year_id' => $yearB->id, 'grade_level_id' => $gradeLevelB->id]);
        $studentB = Student::factory()->for($schoolB)->create([
            'academic_year_id' => $yearB->id, 'current_grade_level_id' => $gradeLevelB->id, 'current_section_id' => $sectionB->id,
        ]);

        $response = $this->actingAsInSchool($adminA)->getJson("/api/v1/students/{$studentB->id}");

        $response->assertStatus(404);
    }

    public function test_super_admin_can_see_students_across_every_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $superAdmin = $this->createSuperAdmin();

        foreach ([$schoolA, $schoolB] as $school) {
            $year = AcademicYear::factory()->for($school)->create();
            $gradeLevel = GradeLevel::factory()->for($school)->create();
            $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);
            Student::factory()->for($school)->create([
                'academic_year_id' => $year->id, 'current_grade_level_id' => $gradeLevel->id, 'current_section_id' => $section->id,
            ]);
        }

        $response = $this->actingAsInSchool($superAdmin)->getJson('/api/v1/students?per_page=50');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_from_school_a_cannot_update_role_from_school_b(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $adminA = $this->createUserWithRole($schoolA, 'School Admin');
        $roleB = \Spatie\Permission\Models\Role::query()->where('school_id', $schoolB->id)->where('name', 'Teacher')->firstOrFail();

        $response = $this->actingAsInSchool($adminA)->putJson("/api/v1/roles/{$roleB->id}", ['name' => 'Hacked']);

        // adminA's permission team context is school A, so school B's Teacher role
        // is simply not "theirs" to touch — RolePolicy still says "you have
        // roles.edit", but the row itself belongs to a different tenant.
        $response->assertStatus(403);
    }
}
