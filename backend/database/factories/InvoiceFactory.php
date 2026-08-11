<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 1000, 10000);

        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => InvoiceStatus::Issued,
            'subtotal' => $total,
            'discount_total' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'credit_total' => 0,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => InvoiceStatus::Draft]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'amount_paid' => $attributes['total'],
        ]);
    }
}
