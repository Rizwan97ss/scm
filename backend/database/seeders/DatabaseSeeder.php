<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: each seeder depends on state the previous one created
     * (permissions before roles, the school before anything scoped to it,
     * roles before any user gets assigned one, academic structure before
     * students can be admitted into a section).
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            PlanSeeder::class,
            SchoolSeeder::class,
            RolePermissionSeeder::class,
            SuperAdminUserSeeder::class,
            SettingSeeder::class,
            AcademicStructureDemoSeeder::class,
            DemoStudentSeeder::class,
        ]);
    }
}
