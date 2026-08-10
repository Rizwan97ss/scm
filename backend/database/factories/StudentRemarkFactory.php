<?php

namespace Database\Factories;

use App\Enums\RemarkCategory;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentRemarkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'author_id' => User::factory(),
            'category' => RemarkCategory::General,
            'body' => fake()->sentence(),
            'visible_to_guardian' => true,
        ];
    }
}
