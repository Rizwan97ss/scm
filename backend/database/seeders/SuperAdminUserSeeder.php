<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * TODO(tenancy): this whole seeder is obsolete under database-per-tenant --
 * Super Admin becomes a landlord-side PlatformUser (its own model/guard,
 * living in the central database), not a User row with school_id === null.
 * User no longer even has a school_id column to set null. Needs Sub-phase
 * E's PlatformAdminSeeder replacement, not a patch. Left in place
 * (unremoved from DatabaseSeeder) as a known-broken placeholder.
 */
class SuperAdminUserSeeder extends Seeder
{
    public function run(): void
    {
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
