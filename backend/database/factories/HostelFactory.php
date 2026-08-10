<?php

namespace Database\Factories;

use App\Enums\HostelType;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class HostelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->words(2, true).' Hostel',
            'type' => fake()->randomElement(HostelType::cases()),
            'address' => fake()->address(),
            'warden_name' => fake()->name(),
            'warden_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
