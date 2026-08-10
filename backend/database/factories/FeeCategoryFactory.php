<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->randomElement(['Tuition', 'Transport', 'Library', 'Laboratory', 'Admission', 'Examination', 'Miscellaneous']),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
