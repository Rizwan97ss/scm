<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'registration_number' => strtoupper(fake()->unique()->bothify('??-##-??-####')),
            'capacity' => fake()->numberBetween(20, 50),
            'driver_name' => fake()->name(),
            'driver_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
