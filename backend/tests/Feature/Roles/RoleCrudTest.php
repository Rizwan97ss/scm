<?php

namespace Tests\Feature\Roles;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class RoleCrudTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_create_a_custom_role_with_permissions(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/roles', [
            'name' => 'Exam Coordinator',
            'permissions' => ['students.view', 'academic-years.view'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Exam Coordinator');

        $this->assertEqualsCanonicalizing(
            ['students.view', 'academic-years.view'],
            $response->json('data.permissions'),
        );
    }

    public function test_super_admin_role_cannot_be_edited_by_non_super_admins(): void
    {
        // Super Admin itself bypasses every check by design (Gate::before), so this
        // guard specifically protects the role from everyone ELSE — School Admin here.
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $role = \Spatie\Permission\Models\Role::query()->where('name', 'Super Admin')->firstOrFail();

        $updateAsAdmin = $this->actingAsInSchool($admin)->putJson("/api/v1/roles/{$role->id}", ['name' => 'Hacked']);
        $updateAsAdmin->assertStatus(403);

        $deleteAsAdmin = $this->actingAsInSchool($admin)->deleteJson("/api/v1/roles/{$role->id}");
        $deleteAsAdmin->assertStatus(403);
    }

    public function test_role_permissions_are_scoped_per_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $adminA = $this->createUserWithRole($schoolA, 'School Admin');

        $response = $this->actingAsInSchool($adminA)->getJson('/api/v1/roles');

        $response->assertOk();
        $roleNames = collect($response->json('data'))->pluck('name');

        $this->assertTrue($roleNames->contains('School Admin'));
        // Every role returned must belong to school A's own team scope, not leak school B's copy.
        $this->assertCount(
            \Spatie\Permission\Models\Role::query()->where('school_id', $schoolA->id)->count(),
            $roleNames
        );
    }

    public function test_teacher_cannot_view_roles(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/roles');

        $response->assertStatus(403);
    }
}
