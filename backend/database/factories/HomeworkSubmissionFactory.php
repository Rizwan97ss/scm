<?php

namespace Database\Factories;

use App\Enums\HomeworkSubmissionStatus;
use App\Models\Homework;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeworkSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'homework_id' => Homework::factory(),
            'student_id' => Student::factory(),
            'status' => HomeworkSubmissionStatus::Submitted,
            'content' => fake()->sentence(),
            'submitted_at' => now(),
        ];
    }
}
