<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => Audience::All,
            'channels' => ['in_app'],
            'recipient_count' => 0,
            'sent_by' => User::factory(),
            'sent_at' => now(),
        ];
    }
}
