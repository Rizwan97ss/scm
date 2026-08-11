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

        tenancy()->initialize($schoolB);
        $yearB = AcademicYear::factory()->create();
        tenancy()->initialize($schoolB);
        $gradeLevelB = GradeLevel::factory()->create();
        tenancy()->initialize($schoolB);
        $sectionB = Section::factory()->create(['academic_year_id' => $yearB->id, 'grade_level_id' => $gradeLevelB->id]);
        tenancy()->initialize($schoolB);
        Student::factory()->create([
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

        tenancy()->initialize($schoolB);
        $yearB = AcademicYear::factory()->create();
        tenancy()->initialize($schoolB);
        $gradeLevelB = GradeLevel::factory()->create();
        tenancy()->initialize($schoolB);
        $sectionB = Section::factory()->create(['academic_year_id' => $yearB->id, 'grade_level_id' => $gradeLevelB->id]);
        tenancy()->initialize($schoolB);
        $studentB = Student::factory()->create([
            'academic_year_id' => $yearB->id, 'current_grade_level_id' => $gradeLevelB->id, 'current_section_id' => $sectionB->id,
        ]);

        $response = $this->actingAsInSchool($adminA)->getJson("/api/v1/students/{$studentB->id}");

        $response->assertStatus(404);
    }

}
