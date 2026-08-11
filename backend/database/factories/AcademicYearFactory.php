<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2100);
        $start = Carbon::create($startYear, 9, 1);
        $end = $start->copy()->addMonths(10);

        return [
            'name' => "{$start->year}-{$end->year}",
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'is_current' => false,
            'status' => 'active',
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }
}
