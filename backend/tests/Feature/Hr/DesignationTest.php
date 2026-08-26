<?php

namespace Tests\Feature\Hr;

use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class DesignationTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_hr_staff_can_create_a_designation(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');

        $response = $this->actingAsInSchool($hr)->postJson('/api/v1/designations', ['name' => 'Math Teacher']);

        $response->assertCreated();
        $this->assertDatabaseHas('designations', ['name' => 'Math Teacher']);
    }

    public function test_teacher_cannot_create_a_designation(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/designations', ['name' => 'Math Teacher']);

        $response->assertStatus(403);
    }

    public function test_a_user_can_be_assigned_a_designation(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        $teacher = $this->createUserWithRole($school, 'Teacher');
        tenancy()->initialize($school);
        $designation = Designation::factory()->create();

        $response = $this->actingAsInSchool($hr)->putJson("/api/v1/users/{$teacher->id}", [
            'designation_id' => $designation->id,
            'employee_id' => 'EMP-0001',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'designation_id' => $designation->id, 'employee_id' => 'EMP-0001']);
    }
}
