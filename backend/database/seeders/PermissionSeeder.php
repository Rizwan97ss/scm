<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every "module.action" Permission row from config('permissions.modules').
 * Permissions themselves are global (not team-scoped); only Roles are per-school.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions.modules') as $module => $actions) {
            foreach ($actions as $action) {
                Permission::query()->firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
