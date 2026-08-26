<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\School;
use App\Notifications\PaymentFailed;
use App\Notifications\TrialEndingSoon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extends Cashier's own webhook handling (which syncs its `subscriptions`
 * table) to also sync schools.billing_status — the fast-read cache other
 * parts of the app (access gating, the admin console) read instead of
 * joining to `subscriptions` on every request. billing_status always stores
 * Stripe's own raw status string directly (see docs/database.md), so this
 * is a pass-through, not a translation layer.
 */
class StripeWebhookController extends CashierWebhookController
{
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionCreated($payload);

        if ($school = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $this->syncFromSubscriptionPayload($school, $payload['data']['object']);
        }

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload): ?Response
    {
        $response = parent::handleCustomerSubscriptionUpdated($payload);

        if ($school = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $this->syncFromSubscriptionPayload($school, $payload['data']['object']);
        }

        return $response;
    }

    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        if ($school = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $school->update(['billing_status' => 'canceled']);
        }

        return $response;
    }

    /**
     * Stripe fires this ~3 days before a trial ends. No base handler exists
     * for it (Cashier doesn't touch billing_status at all) — this is purely
     * a heads-up notification, no state change.
     */
    protected function handleCustomerSubscriptionTrialWillEnd(array $payload): Response
    {
        if ($school = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            Notification::route('mail', $school->email)->notify(new TrialEndingSoon($school));
        }

        return $this->successMethod();
    }

    /**
     * A successful invoice payment is a positive signal, but does NOT mean
     * the subscription is now 'active' — a $0 invoice can succeed while a
     * subscription is still legitimately 'trialing' (confirmed live: this
     * event and customer.subscription.created both fire for a new trial
     * subscription, and naively hardcoding 'active' here raced ahead of and
     * overwrote the correct 'trialing' status the moment both webhooks were
     * delivered). Mirrors whatever the linked subscription's OWN current
     * status actually is instead — that's always authoritative, synced by
     * the customer.subscription.* handlers above. Only falls back to a
     * hardcoded 'active' when there's no subscription context to read (a
     * one-off invoice) and the school was previously locked for a failed
     * payment, since a successful payment is still good news either way.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $response = parent::handleInvoicePaymentSucceeded($payload);

        if ($school = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $subscriptionId = $payload['data']['object']['parent']['subscription_details']['subscription'] ?? null;
            $subscription = $subscriptionId ? $school->subscriptions()->where('stripe_id', $subscriptionId)->first() : null;

            if ($subscription) {
                $school->update(['billing_status' => $subscription->stripe_status]);
            } elseif ($school->billing_status === 'past_due') {
                $school->update(['billing_status' => 'active']);
            }
        }

        return $response;
    }

    /**
     * No base handler exists for this event. Deliberately does NOT lock the
     * school on the first failure — Stripe's own Smart Retries attempt
     * several more times before the subscription reaches a terminal
     * canceled/unpaid status (synced via the subscription-level events
     * above); this just reflects the grace period and notifies.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        if ($school = $this->getUserByStripeId($payload['data']['object']['customer'])) {
            $school->update(['billing_status' => 'past_due']);
            Notification::route('mail', $school->email)->notify(new PaymentFailed($school));
        }

        return $this->successMethod();
    }

    private function syncFromSubscriptionPayload(School $school, array $data): void
    {
        $school->update([
            'billing_status' => $data['status'],
            'trial_ends_at' => isset($data['trial_end']) ? Carbon::createFromTimestamp($data['trial_end']) : null,
        ]);
    }
}
