<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentTransportAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'route_id' => Route::factory(),
            'route_stop_id' => RouteStop::factory(),
            'vehicle_id' => null,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ];
    }
}
