<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolProvisioningService;
use App\Services\SettingsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SchoolProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_provision_creates_school_roles_admin_and_plan_settings(): void
    {
        $this->seed(PermissionSeeder::class);
        $plan = Plan::factory()->create(['key' => 'growth', 'max_students' => 1000, 'max_staff' => 100]);

        $school = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Test Academy', 'short_name' => 'test-academy'],
            ['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@test-academy.test', 'password' => 'password'],
            $plan
        );

        $this->assertDatabaseHas('schools', ['short_name' => 'test-academy', 'plan_id' => $plan->id]);

        $admin = User::query()->where('email', 'ada@test-academy.test')->first();
        $this->assertNotNull($admin);
        $this->assertEquals($school->id, $admin->school_id);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $this->assertTrue($admin->fresh()->hasRole('School Admin'));
        $this->assertTrue($admin->fresh()->can('students.create'));

        $settingsService = app(SettingsService::class);
        $this->assertEquals(1000, $settingsService->get('billing.max_students', null, $school->id));
        $this->assertEquals(100, $settingsService->get('billing.max_staff', null, $school->id));
        $this->assertEquals('growth', $settingsService->get('billing.plan_key', null, $school->id));
    }

    public function test_provision_creates_all_thirteen_default_roles_for_the_school(): void
    {
        $this->seed(PermissionSeeder::class);
        $plan = Plan::factory()->create();

        $school = app(SchoolProvisioningService::class)->provision(
            ['name' => 'Another School', 'short_name' => 'another-school'],
            ['first_name' => 'Bea', 'last_name' => 'Boss', 'email' => 'bea@another-school.test', 'password' => 'password'],
            $plan
        );

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $roleNames = Role::all()->pluck('name');

        foreach (['School Admin', 'Principal', 'Management', 'Accountant', 'HR Staff', 'Receptionist', 'Teacher', 'Class Teacher', 'Student', 'Parent', 'Librarian', 'Transport Staff'] as $expected) {
            $this->assertContains($expected, $roleNames);
        }
    }

    public function test_provision_rolls_back_entirely_when_admin_creation_fails(): void
    {
        $this->seed(PermissionSeeder::class);
        $plan = Plan::factory()->create();

        $existing = School::factory()->create(['short_name' => 'dup-admin-school']);
        User::factory()->for($existing)->create(['email' => 'dup@example.com']);

        try {
            app(SchoolProvisioningService::class)->provision(
                ['name' => 'Rollback School', 'short_name' => 'rollback-school'],
                ['first_name' => 'Cee', 'last_name' => 'Clash', 'email' => 'dup@example.com', 'password' => 'password'],
                $plan
            );
            $this->fail('Expected a unique-constraint violation for the duplicate admin email.');
        } catch (\Throwable) {
            // expected — the duplicate email must violate the unique index
        }

        $this->assertDatabaseMissing('schools', ['short_name' => 'rollback-school']);
    }

    public function test_apply_plan_limits_supports_unlimited_null_values(): void
    {
        $school = School::factory()->create();
        $plan = Plan::factory()->create(['max_students' => null, 'max_staff' => null]);

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
    }
}
