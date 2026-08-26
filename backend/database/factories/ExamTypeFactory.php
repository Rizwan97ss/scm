<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExamTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Exam Type',
            'code' => fake()->unique()->slug(2),
            'sequence' => 0,
            'is_active' => true,
        ];
    }
}
