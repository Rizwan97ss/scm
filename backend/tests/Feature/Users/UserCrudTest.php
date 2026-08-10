<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_create_a_user_with_roles(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/users', [
            'first_name' => 'Tina',
            'last_name' => 'Teacher',
            'email' => 'tina.teacher@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['Teacher'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'tina.teacher@example.com')
            ->assertJsonPath('data.roles.0', 'Teacher');

        $this->assertDatabaseHas('users', ['email' => 'tina.teacher@example.com', 'school_id' => $school->id]);
    }

    public function test_teacher_cannot_create_a_user(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->postJson('/api/v1/users', [
            'first_name' => 'Tina',
            'last_name' => 'Teacher',
            'email' => 'tina.teacher@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['Teacher'],
        ]);

        $response->assertStatus(403);
    }

    public function test_user_index_only_lists_users_from_own_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $admin = $this->createUserWithRole($schoolA, 'School Admin');
        $this->createUserWithRole($schoolA, 'Teacher', ['first_name' => 'InSchoolA']);
        $this->createUserWithRole($schoolB, 'Teacher', ['first_name' => 'InSchoolB']);

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/users?per_page=50');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name');

        $this->assertTrue($names->contains('InSchoolA'));
        $this->assertFalse($names->contains('InSchoolB'));
    }

    public function test_user_cannot_delete_self(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user_status(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($admin)->postJson("/api/v1/users/{$teacher->id}/status", [
            'status' => 'suspended',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'status' => 'suspended']);
    }
}
