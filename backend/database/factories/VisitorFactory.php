<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'purpose' => fake()->randomElement(['Admission enquiry', 'Meeting with teacher', 'Fee payment', 'Delivery', 'Other']),
            'whom_to_meet' => fake()->name(),
            'check_in_time' => now(),
            'check_out_time' => null,
            'notes' => null,
            'logged_by' => User::factory(),
        ];
    }
}
