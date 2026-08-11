<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryStructureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'basic_salary' => fake()->randomFloat(2, 2000, 8000),
            'allowances' => fake()->randomFloat(2, 100, 500),
            'deductions' => fake()->randomFloat(2, 0, 200),
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
