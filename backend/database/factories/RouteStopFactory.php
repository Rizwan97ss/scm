<?php

namespace Database\Factories;

use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteStopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'name' => fake()->streetName(),
            'sequence' => 1,
        ];
    }
}
