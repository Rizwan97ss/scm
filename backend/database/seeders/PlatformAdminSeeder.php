<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformUser;
use Illuminate\Database\Seeder;

/**
 * Seeds the first platform operator ("Super Admin" / owner) account —
 * replaces the old SuperAdminUserSeeder, which predates the database-per-
 * tenant conversion and tried to create a tenant `User` with
 * `school_id => null`. That column and that whole model no longer apply:
 * Super Admin is `App\Models\Platform\PlatformUser`, its own model on the
 * landlord connection with its own `platform` guard (see
 * docs/architecture.md § Authentication). `updateOrCreate` keeps this
 * idempotent, same as every other seeder in this file — safe to re-run.
 *
 * The email/password below are placeholder bootstrap credentials, not
 * meant to survive into a real deployment — change the password (or the
 * whole account) immediately after first login in any environment real
 * users will reach.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        PlatformUser::query()->updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => 'password',
            ]
        );

        $this->command?->info('Platform admin seeded: superadmin@example.com / password');
    }
}
