<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class AcademicYearTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_admin_can_create_an_academic_year(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/academic-years', [
            'name' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', '2026-2027');
        $this->assertDatabaseHas('academic_years', ['school_id' => $school->id, 'name' => '2026-2027']);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/academic-years', [
            'name' => 'Bad Year',
            'start_date' => '2026-09-01',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_activating_a_year_deactivates_the_previous_current_year(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $current = AcademicYear::factory()->for($school)->create(['is_current' => true]);
        $next = AcademicYear::factory()->for($school)->create(['is_current' => false]);

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/academic-years/{$next->id}/activate");

        $response->assertOk()->assertJsonPath('data.is_current', true);
        $this->assertFalse($current->fresh()->is_current);
        $this->assertTrue($next->fresh()->is_current);
    }

    public function test_teacher_can_view_but_not_create_academic_years(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');
        AcademicYear::factory()->for($school)->create();

        $this->actingAsInSchool($teacher)->getJson('/api/v1/academic-years')->assertOk();

        $this->actingAsInSchool($teacher)->postJson('/api/v1/academic-years', [
            'name' => '2030-2031',
            'start_date' => '2030-09-01',
            'end_date' => '2031-06-30',
        ])->assertStatus(403);
    }
}
