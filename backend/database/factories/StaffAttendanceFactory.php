<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffAttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(['present', 'absent', 'late', 'half_day', 'on_leave']),
            'marked_by' => User::factory(),
        ];
    }
}
