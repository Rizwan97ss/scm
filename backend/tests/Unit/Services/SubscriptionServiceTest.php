<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\School;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the parts of SubscriptionService reachable without a live Stripe
 * API call. createCheckoutSession() and the actual Stripe side-effects of
 * swapPlan()/cancel() (when a subscription genuinely exists) require real
 * test-mode credentials and are verified manually — see docs/roadmap.md's
 * Phase 6 notes.
 */
class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_swap_plan_updates_local_plan_and_limits_even_without_an_active_subscription(): void
    {
        $school = School::factory()->create(['plan_id' => null]);
        $newPlan = Plan::factory()->create(['key' => 'scale', 'max_students' => null, 'max_staff' => null]);

        app(SubscriptionService::class)->swapPlan($school, $newPlan);

        $this->assertEquals($newPlan->id, $school->fresh()->plan_id);

        $school->run(function () use ($school) {
            $settings = app(SettingsService::class)->allForSchool($school->id);
            $this->assertNull($settings['billing.max_students']);
            $this->assertEquals('scale', $settings['billing.plan_key']);
        });
    }

    public function test_cancel_does_not_throw_when_school_has_no_active_subscription(): void
    {
        $school = School::factory()->create();

        app(SubscriptionService::class)->cancel($school);

        $this->assertTrue(true);
    }
}
