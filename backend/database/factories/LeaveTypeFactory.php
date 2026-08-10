<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->randomElement(['Casual Leave', 'Sick Leave', 'Earned Leave', 'Unpaid Leave', 'Maternity Leave']),
            'days_allowed_per_year' => 12,
            'is_paid' => true,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
