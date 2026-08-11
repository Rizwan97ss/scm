<?php

namespace Database\Factories;

use App\Enums\HostelAllocationStatus;
use App\Models\HostelRoom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class HostelAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'hostel_room_id' => HostelRoom::factory(),
            'bed_number' => (string) fake()->numberBetween(1, 4),
            'allocated_date' => now()->toDateString(),
            'vacated_date' => null,
            'status' => HostelAllocationStatus::Allocated,
        ];
    }
}
