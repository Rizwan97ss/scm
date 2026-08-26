<?php

namespace Database\Factories;

use App\Models\Hostel;
use Illuminate\Database\Eloquent\Factories\Factory;

class HostelRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'room_number' => (string) fake()->unique()->numberBetween(100, 999),
            'capacity' => fake()->numberBetween(2, 4),
            'is_active' => true,
        ];
    }
}
