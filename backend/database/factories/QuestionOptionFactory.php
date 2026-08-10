<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'question_id' => Question::factory(),
            'option_text' => fake()->words(2, true),
            'is_correct' => false,
            'sequence' => 0,
        ];
    }
}
