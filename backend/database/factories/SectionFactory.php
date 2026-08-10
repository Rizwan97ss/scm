<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'name' => fake()->randomElement(['A', 'B', 'C']),
            'capacity' => 25,
        ];
    }
}
