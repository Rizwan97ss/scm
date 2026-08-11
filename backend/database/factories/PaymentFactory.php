<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'student_id' => Student::factory(),
            'payment_number' => 'RCT-'.fake()->unique()->numerify('####'),
            'amount' => fake()->randomFloat(2, 500, 5000),
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'gateway' => 'manual',
            'reference_number' => null,
            'paid_at' => now()->toDateString(),
            'notes' => null,
            'received_by' => User::factory(),
        ];
    }
}
