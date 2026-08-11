<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'name' => "Room {$number}",
            'code' => "R{$number}",
            'capacity' => fake()->numberBetween(20, 40),
            'type' => 'classroom',
        ];
    }
}
