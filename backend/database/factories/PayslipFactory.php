<?php

namespace Database\Factories;

use App\Enums\PayslipStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayslipFactory extends Factory
{
    public function definition(): array
    {
        $basic = fake()->randomFloat(2, 2000, 8000);
        $allowances = fake()->randomFloat(2, 100, 500);
        $deductions = fake()->randomFloat(2, 0, 200);

        return [
            'user_id' => User::factory(),
            'salary_structure_id' => null,
            'payslip_number' => 'PS-'.fake()->unique()->numerify('####'),
            'month' => now()->month,
            'year' => now()->year,
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => round($basic + $allowances - $deductions, 2),
            'status' => PayslipStatus::Generated,
            'generated_by' => User::factory(),
        ];
    }
}
