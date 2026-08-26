<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\School;
use Laravel\Cashier\Checkout;

/**
 * Thin wrapper around Cashier's Billable methods on School, kept as its own
 * service (rather than calling Cashier directly from controllers) so plan
 * changes stay in sync with the local billing.max_students/max_staff
 * Settings projection Stripe itself has no knowledge of — see
 * SchoolProvisioningService::applyPlanLimits().
 */
class SubscriptionService
{
    public function __construct(private readonly SchoolProvisioningService $provisioning) {}

    /**
     * Starts a hosted Stripe Checkout session for a brand-new subscription.
     * Does NOT create a local `subscriptions` row — that happens later, via
     * the webhook, once the customer actually completes payment on Stripe's
     * page (checkout.session.completed / customer.subscription.created).
     */
    public function createCheckoutSession(School $school, Plan $plan, string $successUrl, string $cancelUrl): Checkout
    {
        return $school->newSubscription('default', $plan->stripe_price_id)
            ->trialDays($plan->trial_days)
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
    }

    /**
     * Changes an already-subscribed school's plan. Updates Stripe, the
     * local `plan_id`, and re-projects the new plan's seat limits — all
     * three must move together or the app and Stripe silently disagree
     * about what the school is entitled to.
     */
    public function swapPlan(School $school, Plan $plan): void
    {
        $school->subscription('default')?->swap($plan->stripe_price_id);

        $school->update(['plan_id' => $plan->id]);

        // applyPlanLimits() writes to Settings, which lives in the school's
        // own tenant database (see SettingsService's docblock). Today's only
        // caller (SchoolPlanController) is platform-guarded -- no tenant is
        // ever active there -- and a future School-Admin-facing "change
        // plan" action would call this from a tenant-zone request instead;
        // run() makes swapPlan() correct from either without relying on
        // whichever caller happens to invoke it to get tenancy right first.
        $school->run(fn () => $this->provisioning->applyPlanLimits($school, $plan));
    }

    /**
     * Marks the subscription to cancel at the end of the current billing
     * period (Cashier/Stripe default) — does not immediately revoke access.
     * `billing_status` transitions to 'canceled' later, via the
     * customer.subscription.deleted webhook, once the period actually ends.
     */
    public function cancel(School $school): void
    {
        $school->subscription('default')?->cancel();
    }
}
