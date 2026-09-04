<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseMaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'type' => 'document',
            'url' => null,
            'is_published' => true,
        ];
    }
}
