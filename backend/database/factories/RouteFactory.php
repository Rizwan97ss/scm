<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Route '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
