<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class SectionTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_admin_can_list_and_create_sections(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();

        $this->actingAsInSchool($admin)->getJson('/api/v1/sections')->assertOk();

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/sections', [
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeLevel->id,
            'name' => 'A',
            'capacity' => 30,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'A');
        $this->assertDatabaseHas('sections', ['school_id' => $school->id, 'name' => 'A']);
    }

    /**
     * Regression test for a Section-update fatal error: SectionPolicy::update()
     * once narrowed its parameter type from the base policy's Model to Section,
     * which PHP treats as a fatal class-declaration error the moment the class
     * is loaded — breaking every request that touched SectionPolicy at all,
     * including a plain index list. No test exercised Section endpoints via
     * HTTP before, so it went uncaught until manual browser testing found it.
     */
    public function test_class_teacher_can_update_their_own_section_without_blanket_permission(): void
    {
        $school = $this->createSchool();
        $classTeacher = $this->createUserWithRole($school, 'Class Teacher');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create([
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'class_teacher_id' => $classTeacher->id,
        ]);

        $response = $this->actingAsInSchool($classTeacher)->putJson("/api/v1/sections/{$section->id}", [
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'A', 'capacity' => 28,
        ]);

        $response->assertOk()->assertJsonPath('data.capacity', 28);
    }

    public function test_teacher_cannot_update_a_section_they_do_not_lead(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        $year = AcademicYear::factory()->for($school)->create();
        $gradeLevel = GradeLevel::factory()->for($school)->create();
        $section = Section::factory()->for($school)->create(['academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id]);

        $response = $this->actingAsInSchool($teacher)->putJson("/api/v1/sections/{$section->id}", [
            'academic_year_id' => $year->id, 'grade_level_id' => $gradeLevel->id, 'name' => 'B',
        ]);

        $response->assertStatus(403);
    }
}
