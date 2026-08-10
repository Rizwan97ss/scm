<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteStopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'route_id' => Route::factory(),
            'name' => fake()->streetName(),
            'sequence' => 1,
        ];
    }
}
