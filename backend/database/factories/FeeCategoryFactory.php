<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FeeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Tuition', 'Transport', 'Library', 'Laboratory', 'Admission', 'Examination', 'Miscellaneous']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
