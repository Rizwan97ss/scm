<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_school_admin_can_view_their_own_billing_status(): void
    {
        $plan = Plan::factory()->create(['name' => 'Growth', 'max_students' => 1000]);
        $school = $this->createSchool(['plan_id' => $plan->id, 'billing_status' => 'trialing']);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/billing');

        $response->assertOk()
            ->assertJsonPath('data.plan.name', 'Growth')
            ->assertJsonPath('data.billing_status', 'trialing');
    }

    public function test_teacher_cannot_view_billing(): void
    {
        $school = $this->createSchool();
        $teacher = $this->createUserWithRole($school, 'Teacher');

        $response = $this->actingAsInSchool($teacher)->getJson('/api/v1/billing');

        $response->assertForbidden();
    }

    public function test_portal_returns_a_clean_error_when_stripe_is_not_set_up_yet(): void
    {
        $school = $this->createSchool(['stripe_id' => null]);
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/billing/portal');

        $response->assertStatus(422);
    }
}
