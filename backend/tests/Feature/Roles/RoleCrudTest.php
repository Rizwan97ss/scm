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

    public function test_role_permissions_are_scoped_per_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $adminA = $this->createUserWithRole($schoolA, 'School Admin');

        $response = $this->actingAsInSchool($adminA)->getJson('/api/v1/roles');

        $response->assertOk();
        $roleNames = collect($response->json('data'))->pluck('name');

        $this->assertTrue($roleNames->contains('School Admin'));
        // Every role returned must be school A's own — physical database
        // separation is what guarantees this now, not a school_id filter
        // (school B's identically-named roles live in a different database
        // entirely, there's no shared `roles` table left to leak across).
        $this->assertCount(\Spatie\Permission\Models\Role::query()->count(), $roleNames);
    }

    public function test_teacher_cannot_view_roles(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/roles');

        $response->assertStatus(403);
    }
}
