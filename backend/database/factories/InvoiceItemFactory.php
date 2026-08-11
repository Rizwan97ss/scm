<?php

namespace Database\Factories;

use App\Models\FeeCategory;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $unitAmount = fake()->randomFloat(2, 500, 5000);

        return [
            'invoice_id' => Invoice::factory(),
            'fee_structure_id' => null,
            'fee_category_id' => FeeCategory::factory(),
            'description' => fake()->words(3, true),
            'quantity' => 1,
            'unit_amount' => $unitAmount,
            'amount' => $unitAmount,
        ];
    }
}
