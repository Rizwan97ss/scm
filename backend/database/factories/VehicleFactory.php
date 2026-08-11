<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'registration_number' => strtoupper(fake()->unique()->bothify('??-##-??-####')),
            'capacity' => fake()->numberBetween(20, 50),
            'driver_name' => fake()->name(),
            'driver_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
