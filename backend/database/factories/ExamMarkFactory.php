<?php

namespace Database\Factories;

use App\Models\ExamSubject;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamMarkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exam_subject_id' => ExamSubject::factory(),
            'student_id' => Student::factory(),
            'marks_obtained' => fake()->numberBetween(40, 100),
            'is_absent' => false,
            'entered_by' => User::factory(),
        ];
    }
}
