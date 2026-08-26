<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeworkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'due_date' => now()->addDays(7)->toDateString(),
            'max_score' => 100,
        ];
    }
}
