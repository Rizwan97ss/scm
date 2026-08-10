<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $user = User::query()->updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'school_id' => null,
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }

        $this->command?->info('Super Admin seeded: superadmin@example.com / password');
    }
}
