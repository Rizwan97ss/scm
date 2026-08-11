<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TimetablePeriodFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 10);

        return [
            'name' => "Period {$n}",
            'start_time' => sprintf('%02d:00', 7 + $n),
            'end_time' => sprintf('%02d:00', 8 + $n),
            'sequence' => $n,
            'is_break' => false,
        ];
    }
}
