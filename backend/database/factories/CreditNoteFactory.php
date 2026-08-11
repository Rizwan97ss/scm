<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'credit_note_number' => 'CN-'.fake()->unique()->numerify('####'),
            'amount' => fake()->randomFloat(2, 100, 1000),
            'reason' => fake()->sentence(),
            'issued_by' => User::factory(),
            'issued_at' => now()->toDateString(),
        ];
    }
}
