<?php

namespace Database\Factories;

use App\Enums\RemarkCategory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentRemarkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'author_id' => User::factory(),
            'category' => RemarkCategory::General,
            'body' => fake()->sentence(),
            'visible_to_guardian' => true,
        ];
    }
}
