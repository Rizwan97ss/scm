<?php

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class EnsureSchoolIsUsableTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_null_billing_status_is_full_access_legacy_grandfather(): void
    {
        $school = $this->createSchool(['billing_status' => null]);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/students');

        $response->assertOk();
    }

    public function test_trialing_school_has_full_access(): void
    {
        $school = $this->createSchool(['billing_status' => 'trialing']);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/students');

        $response->assertOk();
    }

    public function test_past_due_school_can_read_but_not_write(): void
    {
        $school = $this->createSchool(['billing_status' => 'past_due']);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($admin)->getJson('/api/v1/students')->assertOk();

        $response = $this->actingAsInSchool($admin)->postJson('/api/v1/academic-years', ['name' => 'Blocked Year', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        $response->assertStatus(402);
    }

    public function test_canceled_school_is_fully_locked(): void
    {
        $school = $this->createSchool(['billing_status' => 'canceled']);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/students');

        $response->assertStatus(402);
    }

    public function test_inactive_school_is_locked_regardless_of_billing_status(): void
    {
        $school = $this->createSchool(['billing_status' => 'active', 'is_active' => false]);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/students');

        $response->assertStatus(403);
    }

    public function test_canceled_school_can_still_reach_auth_me_and_billing_endpoints(): void
    {
        $school = $this->createSchool(['billing_status' => 'canceled']);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $this->actingAsInSchool($admin)->getJson('/api/v1/auth/me')->assertOk();
        $this->actingAsInSchool($admin)->getJson('/api/v1/billing')->assertOk();
    }

    public function test_super_admin_is_never_gated(): void
    {
        $this->createSchool(['billing_status' => 'canceled']);
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAsInSchool($superAdmin)->getJson('/api/v1/platform/schools');

        $response->assertOk();
    }
}
