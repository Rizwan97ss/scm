<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Placeholder tiers/pricing for local dev — real pricing is a business
 * decision made later by editing these rows (via the platform admin
 * console). The stripe_product_id/stripe_price_id below point at real
 * test-mode Stripe objects created for this dev Stripe account (see
 * dashboard.stripe.com/test/products) so signup Checkout has something to
 * subscribe to locally; swap them for production Price IDs before going
 * live. Idempotent via updateOrCreate so re-running db:seed never
 * duplicates plans.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'starter',
                'name' => 'Starter',
                'description' => 'For a single small school just getting started.',
                'price_cents' => 2900,
                'trial_days' => 14,
                'max_students' => 200,
                'max_staff' => 20,
                'sort_order' => 1,
                'stripe_product_id' => 'prod_V2egrhuuMAY8x2',
                'stripe_price_id' => 'price_1U2ZNHSs1wEtjr4W6GiATSyv',
            ],
            [
                'key' => 'growth',
                'name' => 'Growth',
                'description' => 'For a growing school that has outgrown spreadsheets.',
                'price_cents' => 7900,
                'trial_days' => 14,
                'max_students' => 1000,
                'max_staff' => 100,
                'sort_order' => 2,
                'stripe_product_id' => 'prod_V2egnEw3jDGMQR',
                'stripe_price_id' => 'price_1U2ZNKSs1wEtjr4WNKbwnLGf',
            ],
            [
                'key' => 'scale',
                'name' => 'Scale',
                'description' => 'For large schools and small districts with no seat limits.',
                'price_cents' => 19900,
                'trial_days' => 14,
                'max_students' => null,
                'max_staff' => null,
                'sort_order' => 3,
                'stripe_product_id' => 'prod_V2egQjRX6T2mLv',
                'stripe_price_id' => 'price_1U2ZNNSs1wEtjr4WI6ylPa6q',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['key' => $plan['key']], $plan);
        }
    }
}
