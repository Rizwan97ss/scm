<?php

namespace Database\Factories;

use App\Models\GradingScale;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeBandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grading_scale_id' => GradingScale::factory(),
            'min_percentage' => 0,
            'max_percentage' => 100,
            'grade_label' => 'A',
            'grade_point' => 4.0,
            'remark' => 'Excellent',
        ];
    }
}
