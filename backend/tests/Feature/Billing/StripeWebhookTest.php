<?php

namespace Tests\Feature\Billing;

use App\Models\School;
use App\Notifications\PaymentFailed;
use App\Notifications\TrialEndingSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Exercises StripeWebhookController against hand-built payloads shaped like
 * real Stripe events — no live Stripe call involved. STRIPE_WEBHOOK_SECRET
 * is blank in the test environment, so VerifyWebhookSignature never attaches
 * (see WebhookController::__construct), which is what makes posting a raw
 * unsigned payload here valid for testing the sync logic in isolation.
 * Checkout Session creation itself (SubscriptionService::createCheckoutSession)
 * is NOT covered here — it requires a live Stripe API call and needs manual
 * verification once real test-mode keys are configured (see docs/roadmap.md).
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function subscriptionItem(): array
    {
        return [
            'id' => 'si_'.fake()->uuid(),
            'price' => ['id' => 'price_test123', 'product' => 'prod_test123'],
            'quantity' => 1,
        ];
    }

    public function test_customer_subscription_created_syncs_billing_status_and_trial_end(): void
    {
        $school = School::factory()->create(['stripe_id' => 'cus_test123', 'billing_status' => null]);
        $trialEnd = now()->addDays(14)->timestamp;

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.created',
            'data' => ['object' => [
                'id' => 'sub_test123',
                'customer' => 'cus_test123',
                'status' => 'trialing',
                'trial_end' => $trialEnd,
                'items' => ['data' => [$this->subscriptionItem()]],
                'metadata' => [],
            ]],
        ]);

        $response->assertOk();
        $school->refresh();
        $this->assertEquals('trialing', $school->billing_status);
        $this->assertEquals($trialEnd, $school->trial_ends_at->timestamp);
        $this->assertDatabaseHas('subscriptions', ['school_id' => $school->id, 'stripe_id' => 'sub_test123']);
    }

    public function test_customer_subscription_updated_syncs_billing_status(): void
    {
        $school = School::factory()->create(['stripe_id' => 'cus_test456', 'billing_status' => 'trialing']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_test456',
                'customer' => 'cus_test456',
                'status' => 'past_due',
                'items' => ['data' => [$this->subscriptionItem()]],
                'metadata' => [],
            ]],
        ]);

        $response->assertOk();
        $this->assertEquals('past_due', $school->fresh()->billing_status);
    }

    public function test_customer_subscription_deleted_sets_canceled(): void
    {
        $school = School::factory()->create(['stripe_id' => 'cus_test789', 'billing_status' => 'active']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_test789',
                'customer' => 'cus_test789',
                'status' => 'canceled',
            ]],
        ]);

        $response->assertOk();
        $this->assertEquals('canceled', $school->fresh()->billing_status);
    }

    public function test_invoice_payment_succeeded_sets_active(): void
    {
        $school = School::factory()->create(['stripe_id' => 'cus_pay_ok', 'billing_status' => 'past_due']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'invoice.payment_succeeded',
            'data' => ['object' => [
                'id' => 'in_test123',
                'customer' => 'cus_pay_ok',
                'metadata' => [],
                'parent' => ['subscription_details' => ['metadata' => []]],
            ]],
        ]);

        $response->assertOk();
        $this->assertEquals('active', $school->fresh()->billing_status);
    }

    public function test_invoice_payment_failed_sets_past_due_and_notifies(): void
    {
        Notification::fake();
        $school = School::factory()->create(['stripe_id' => 'cus_pay_fail', 'billing_status' => 'active', 'email' => 'billing@test-school.test']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_test456',
                'customer' => 'cus_pay_fail',
            ]],
        ]);

        $response->assertOk();
        $this->assertEquals('past_due', $school->fresh()->billing_status);
        Notification::assertSentOnDemand(PaymentFailed::class);
    }

    public function test_trial_will_end_notifies_without_changing_status(): void
    {
        Notification::fake();
        $school = School::factory()->create(['stripe_id' => 'cus_trial_end', 'billing_status' => 'trialing', 'email' => 'billing@another-school.test']);

        $response = $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.trial_will_end',
            'data' => ['object' => [
                'id' => 'sub_trial_end',
                'customer' => 'cus_trial_end',
            ]],
        ]);

        $response->assertOk();
        $this->assertEquals('trialing', $school->fresh()->billing_status);
        Notification::assertSentOnDemand(TrialEndingSoon::class);
    }

    public function test_unknown_stripe_customer_id_is_ignored_without_error(): void
    {
        $response = $this->postJson('/stripe/webhook', [
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_orphan',
                'customer' => 'cus_does_not_exist',
                'status' => 'active',
                'items' => ['data' => [$this->subscriptionItem()]],
                'metadata' => [],
            ]],
        ]);

        $response->assertOk();
    }
}
