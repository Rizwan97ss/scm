<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'mcq',
            'text' => fake()->sentence().'?',
            'default_marks' => 1,
            'created_by' => User::factory(),
        ];
    }
}
