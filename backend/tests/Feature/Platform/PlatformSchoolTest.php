<?php

namespace Tests\Feature\Platform;

use App\Models\Plan;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class PlatformSchoolTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_super_admin_can_list_all_schools_across_tenants(): void
    {
        $schoolA = $this->createSchool(['name' => 'Alpha School']);
        $schoolB = $this->createSchool(['name' => 'Beta School']);
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAsInSchool($superAdmin)->getJson('/api/v1/platform/schools');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains($schoolA->name));
        $this->assertTrue($names->contains($schoolB->name));
    }

    public function test_school_admin_cannot_access_platform_schools(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');

        $response = $this->actingAsInSchool($admin)->getJson('/api/v1/platform/schools');

        $response->assertForbidden();
    }

    public function test_super_admin_can_view_a_schools_detail_with_usage_counts(): void
    {
        $plan = Plan::factory()->create(['max_students' => 100, 'max_staff' => 10]);
        $school = $this->createSchool(['plan_id' => $plan->id]);
        $this->createUserWithRole($school, 'Teacher');
        $this->createUserWithRole($school, 'Student');
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAsInSchool($superAdmin)->getJson("/api/v1/platform/schools/{$school->id}");

        $response->assertOk()
            ->assertJsonPath('data.plan.id', $plan->id)
            ->assertJsonPath('data.usage.max_students', 100)
            ->assertJsonPath('data.usage.max_staff', 10)
            // 1 teacher counts as staff, the Student does not.
            ->assertJsonPath('data.usage.staff', 1);
    }

    public function test_super_admin_can_change_a_schools_plan(): void
    {
        $school = $this->createSchool();
        $superAdmin = $this->createSuperAdmin();
        $newPlan = Plan::factory()->create(['key' => 'scale']);

        $response = $this->actingAsInSchool($superAdmin)
            ->postJson("/api/v1/platform/schools/{$school->id}/plan", ['plan_id' => $newPlan->id]);

        $response->assertOk()->assertJsonPath('data.plan.key', 'scale');
        $this->assertEquals($newPlan->id, $school->fresh()->plan_id);
    }

    public function test_change_plan_rejects_an_inactive_plan(): void
    {
        $school = $this->createSchool();
        $superAdmin = $this->createSuperAdmin();
        $inactivePlan = Plan::factory()->create(['is_active' => false]);

        $response = $this->actingAsInSchool($superAdmin)
            ->postJson("/api/v1/platform/schools/{$school->id}/plan", ['plan_id' => $inactivePlan->id]);

        $response->assertStatus(422);
    }

    public function test_metrics_returns_counts_by_billing_status_and_an_approximate_mrr(): void
    {
        $this->createSchool();
        $superAdmin = $this->createSuperAdmin();
        $plan = Plan::factory()->create(['price_cents' => 5000]);
        School::factory()->create(['billing_status' => 'active', 'plan_id' => $plan->id]);
        School::factory()->create(['billing_status' => 'active', 'plan_id' => $plan->id]);
        School::factory()->create(['billing_status' => 'trialing', 'plan_id' => $plan->id]);

        $response = $this->actingAsInSchool($superAdmin)->getJson('/api/v1/platform/metrics');

        $response->assertOk()
            ->assertJsonPath('data.by_billing_status.active', 2)
            ->assertJsonPath('data.by_billing_status.trialing', 1)
            ->assertJsonPath('data.approximate_mrr_cents', 10000);
    }
}
