<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class TermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'name' => 'Term '.fake()->numberBetween(1, 3),
            'start_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'sequence' => fake()->numberBetween(1, 3),
            'is_current' => false,
        ];
    }
}
