<?php

namespace Tests\Concerns;

use App\Models\School;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lightweight equivalent of the demo seeders for tests: real permissions and
 * real per-school roles (via the actual production seeders, so tests exercise
 * the same matrix admins get), without the heavy demo academic/student data.
 */
trait InteractsWithSchool
{
    protected function createSchool(array $attributes = []): School
    {
        $this->seed(PermissionSeeder::class);

        $school = School::factory()->create($attributes);

        $this->seed(RolePermissionSeeder::class);

        return $school;
    }

    protected function createUserWithRole(School $school, string $role, array $attributes = []): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $user = User::factory()->for($school)->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function createSuperAdmin(array $attributes = []): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $user = User::factory()->superAdmin()->create($attributes);
        $user->assignRole('Super Admin');

        return $user;
    }

    /**
     * Acts as the given user for the request AND sets the matching permission
     * team context, mirroring what EnsureSchoolContext does for a real request
     * (needed here because middleware runs against the request's resolved user,
     * but role/permission setup in the test itself happens before that).
     */
    protected function actingAsInSchool(User $user): static
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id ?? 0);

        return $this->actingAs($user);
    }
}
