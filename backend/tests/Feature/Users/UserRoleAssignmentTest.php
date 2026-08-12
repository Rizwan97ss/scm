<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * Regression coverage for a privilege-escalation bug: HR Staff (default
 * permissions users.create/users.edit, deliberately NOT roles.create/
 * roles.edit) could previously create or promote an account straight to
 * School Admin by simply including it in the `roles` array, since neither
 * UserPolicy::create()/manageRoles() nor the FormRequests restricted which
 * role names could be assigned -- only that the actor could touch users at
 * all. See UserPolicy::canAssignRoles().
 */
class UserRoleAssignmentTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_hr_staff_cannot_create_a_school_admin_account(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');

        $response = $this->actingAsInSchool($hr)->postJson('/api/v1/users', [
            'first_name' => 'Eve',
            'last_name' => 'Escalator',
            'email' => 'eve@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['School Admin'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'eve@example.test']);
    }

    public function test_hr_staff_cannot_promote_an_existing_user_to_school_admin(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($hr)->postJson("/api/v1/users/{$teacher->id}/roles", [
            'roles' => ['School Admin'],
        ]);

        $response->assertStatus(403);
        $this->assertFalse($teacher->fresh()->hasRole('School Admin'));
    }

    public function test_hr_staff_can_still_create_a_non_admin_staff_account(): void
    {
        $school = $this->createSchool();
        $hr = $this->createUserWithRole($school, 'HR Staff');

        $response = $this->actingAsInSchool($hr)->postJson('/api/v1/users', [
            'first_name' => 'Tom',
            'last_name' => 'Teacher',
            'email' => 'tom@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['Teacher'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'tom@example.test']);
    }

    public function test_school_admin_can_create_another_school_admin_account(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/users', [
            'first_name' => 'New',
            'last_name' => 'Admin',
            'email' => 'newadmin@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['School Admin'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'newadmin@example.test']);
    }
}
