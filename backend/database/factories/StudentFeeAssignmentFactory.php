<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\FeeStructure;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFeeAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_structure_id' => FeeStructure::factory(),
            'discount_type' => DiscountType::None,
            'discount_value' => 0,
            'reason' => null,
        ];
    }
}
