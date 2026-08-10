<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Enums\NoticeType;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoticeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'type' => NoticeType::General,
            'audience' => Audience::All,
            'is_published' => false,
            'created_by' => User::factory(),
        ];
    }
}
