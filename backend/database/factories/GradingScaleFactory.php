<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GradingScaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Scale',
            'is_default' => false,
        ];
    }
}
