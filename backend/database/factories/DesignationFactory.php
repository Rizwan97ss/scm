<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class DesignationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
