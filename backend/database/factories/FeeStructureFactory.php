<?php

namespace Database\Factories;

use App\Enums\FeeFrequency;
use App\Models\AcademicYear;
use App\Models\FeeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeeStructureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade_level_id' => null,
            'fee_category_id' => FeeCategory::factory(),
            'name' => fake()->unique()->words(3, true),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'frequency' => fake()->randomElement(FeeFrequency::cases()),
            'due_day_of_month' => null,
            'is_active' => true,
        ];
    }
}
