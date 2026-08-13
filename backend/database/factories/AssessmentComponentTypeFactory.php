<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AssessmentComponentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Written',
            'code' => 'written-'.fake()->unique()->numerify('####'),
            'is_auto_graded' => false,
            'sequence' => 0,
            'is_active' => true,
        ];
    }

    public function onlineMcq(): static
    {
        return $this->state(['name' => 'Online MCQ', 'code' => 'online-mcq-'.fake()->unique()->numerify('####'), 'is_auto_graded' => true]);
    }
}
