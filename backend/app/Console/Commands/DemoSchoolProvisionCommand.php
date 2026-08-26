<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Services\SchoolProvisioningService;
use Database\Seeders\TenantDemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Provisions one fully-seeded demo tenant (school + physical database +
 * admin user + a full spread of realistic data across every module) in one
 * shot -- for local/dev exploration, not part of the production signup path
 * (that's SignupController, which goes through Stripe Checkout instead of
 * a fixed password).
 */
class DemoSchoolProvisionCommand extends Command
{
    protected $signature = 'demo:provision-school
        {name : Full school name, e.g. "Greenwood International School"}
        {short_name : Slug source, e.g. "greenwood-international"}
        {plan=starter : Plan key: starter, growth, or scale}
        {--password=password123 : Admin password}';

    protected $description = 'Provision a new tenant school and fill it with realistic demo data across every module';

    public function handle(SchoolProvisioningService $provisioning): int
    {
        $plan = Plan::query()->where('key', $this->argument('plan'))->first();
        if (! $plan) {
            $this->error("Unknown plan key: {$this->argument('plan')}");

            return self::FAILURE;
        }

        $shortName = $this->argument('short_name');
        $name = $this->argument('name');
        $password = $this->option('password');

        $result = $provisioning->provision(
            [
                'name' => $name,
                'short_name' => $shortName,
                'email' => "hello@{$shortName}.test",
                'billing_status' => 'active',
                'trial_ends_at' => now()->addDays($plan->trial_days),
            ],
            [
                'first_name' => 'School',
                'last_name' => 'Admin',
                'email' => "admin@{$shortName}.test",
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
            $plan,
        );

        $school = $result['school'];

        $school->run(function () use ($school) {
            app(TenantDemoDataSeeder::class)->run($school);
        });

        $this->info('');
        $this->info("Provisioned: {$school->name}");
        $this->info("Domain:   {$school->slug}.localtest.me");
        $this->info("Admin:    admin@{$shortName}.test / {$password}");

        return self::SUCCESS;
    }
}
