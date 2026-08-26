<?php

namespace Database\Seeders;

use App\Models\School;
use App\Services\SchoolProvisioningService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the 13 default roles with their default permission sets. Super Admin
 * is created once under the reserved "global" team id 0 (cross-school); every
 * other role is created per school by delegating to
 * SchoolProvisioningService::seedDefaultRoles() — the single source of truth
 * for the default permission matrix, also used by the self-service signup
 * flow to provision a brand-new tenant at runtime.
 *
 * This is the DEFAULT matrix administrators start with — School Admins can
 * freely create custom roles or adjust these afterward via the Roles UI.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * TODO(tenancy): needs Sub-phase E's rewrite. "Super Admin" as a Spatie
     * Role in team 0 doesn't fit the new shape at all — Super Admin becomes
     * a landlord-side PlatformUser with its own separate guard/authorization,
     * not a Role row inside any tenant database. And the per-school loop
     * below needs to switch into EACH tenant's own connection before calling
     * seedDefaultRoles() -- Role::findOrCreate() must run against that
     * tenant's own `roles` table, not whatever connection happens to be
     * active. Left as a known-broken placeholder rather than patched
     * piecemeal.
     */
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $superAdmin = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->syncPermissions($this->allPermissionNames());

        $provisioning = app(SchoolProvisioningService::class);

        foreach (School::query()->get() as $school) {
            $provisioning->seedDefaultRoles($school);
        }

        $registrar->forgetCachedPermissions();
    }

    private function allPermissionNames(): array
    {
        $names = [];

        foreach (config('permissions.modules') as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }
}
