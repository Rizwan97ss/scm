<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class PermissionRolloutTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_rollout_command_grants_new_permissions_to_an_already_provisioned_schools_admin_role(): void
    {
        $school = $this->createSchool();

        tenancy()->initialize($school);
        // Simulate a school provisioned before Phase 15 shipped — its
        // School Admin role predates users.manage-mfa entirely.
        Permission::query()->where('name', 'users.manage-mfa')->delete();
        $adminRole = Role::findByName('School Admin', 'web');
        $this->assertFalse($adminRole->fresh()->permissions->pluck('name')->contains('users.manage-mfa'));
        tenancy()->end();

        $this->artisan('permissions:rollout', ['--school' => $school->slug])->assertExitCode(0);

        $school->run(function () {
            $adminRole = Role::findByName('School Admin', 'web');
            $this->assertTrue($adminRole->fresh()->permissions->pluck('name')->contains('users.manage-mfa'));
        });
    }
}
