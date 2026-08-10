<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeLevelFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 12);

        return [
            'school_id' => School::factory(),
            'name' => "Grade {$n}",
            'code' => "G{$n}",
            'sequence' => $n,
        ];
    }
}
