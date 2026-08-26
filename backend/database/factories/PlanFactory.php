<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true).' Plan',
            'description' => fake()->sentence(),
            'stripe_product_id' => null,
            'stripe_price_id' => null,
            'price_cents' => fake()->numberBetween(2900, 19900),
            'currency' => 'usd',
            'trial_days' => 14,
            'max_students' => fake()->randomElement([200, 1000, null]),
            'max_staff' => fake()->randomElement([20, 100, null]),
            'feature_flags' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
