<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppNotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'announcement_id' => null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'is_read' => false,
        ];
    }
}
