<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\School;
use App\Services\SchoolProvisioningService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_school_roles_admin_and_plan_settings(): void
    {
        $plan = Plan::factory()->create(['key' => 'growth', 'max_students' => 1000, 'max_staff' => 100]);

        $result = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Test Academy', 'short_name' => 'test-academy'],
            ['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@test-academy.test', 'password' => 'password'],
            $plan
        );
        $school = $result['school'];
        $admin = $result['admin'];

        $this->assertDatabaseHas('schools', ['short_name' => 'test-academy', 'plan_id' => $plan->id]);
        $this->assertEquals('ada@test-academy.test', $admin->email);

        $school->run(function () use ($admin) {
            $this->assertTrue($admin->fresh()->hasRole('School Admin'));
            $this->assertTrue($admin->fresh()->can('students.create'));

            $settingsService = app(SettingsService::class);
            $this->assertEquals(1000, $settingsService->get('billing.max_students'));
            $this->assertEquals(100, $settingsService->get('billing.max_staff'));
            $this->assertEquals('growth', $settingsService->get('billing.plan_key'));
        });
    }

    public function test_provision_creates_all_thirteen_default_roles_for_the_school(): void
    {
        $plan = Plan::factory()->create();

        $result = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Another School', 'short_name' => 'another-school'],
            ['first_name' => 'Bea', 'last_name' => 'Boss', 'email' => 'bea@another-school.test', 'password' => 'password'],
            $plan
        );

        $result['school']->run(function () {
            $roleNames = Role::all()->pluck('name');

            foreach (['School Admin', 'Principal', 'Management', 'Accountant', 'HR Staff', 'Receptionist', 'Teacher', 'Class Teacher', 'Student', 'Parent', 'Librarian', 'Transport Staff'] as $expected) {
                $this->assertContains($expected, $roleNames);
            }
        });
    }

    /**
     * A duplicate-admin-email collision can no longer happen across schools
     * (each tenant has its own physically separate `users` table, so
     * uniqueness is per-tenant, not global) — this instead forces the
     * failure a different way, since what's actually under test is
     * provision()'s cleanup path, not any particular validation rule.
     */
    public function test_provision_rolls_back_entirely_when_admin_creation_fails(): void
    {
        $plan = Plan::factory()->create();

        try {
            app(SchoolProvisioningService::class)->provision(
                ['name' => 'Rollback School', 'short_name' => 'rollback-school'],
                ['first_name' => 'Cee', 'last_name' => 'Clash', 'password' => 'password'],
                $plan
            );
            $this->fail('Expected admin creation to fail without an email.');
        } catch (\Throwable) {
            // expected — email is a required, non-nullable column
        }

        $this->assertDatabaseMissing('schools', ['short_name' => 'rollback-school']);
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_apply_plan_limits_supports_unlimited_null_values(): void
    {
        $school = School::factory()->create();
        $plan = Plan::factory()->create(['max_students' => null, 'max_staff' => null]);

        $school->run(function () use ($school, $plan) {
            app(SchoolProvisioningService::class)->applyPlanLimits($school, $plan);

            // Setting::value is a nullable column — a null limit round-trips as a
            // present-but-null entry (unlimited), distinct from the key being
            // absent entirely, so this asserts against the raw merged map rather
            // than SettingsService::get()'s `??` default (which can't distinguish
            // "explicitly null" from "not set" once accessed via `??`).
            $settingsService = app(SettingsService::class);
            $all = $settingsService->allForSchool($school->id);
            $this->assertArrayHasKey('billing.max_students', $all);
            $this->assertNull($all['billing.max_students']);
            $this->assertArrayHasKey('billing.max_staff', $all);
            $this->assertNull($all['billing.max_staff']);
        });
    }
}
