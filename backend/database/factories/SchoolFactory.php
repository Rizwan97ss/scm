<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' School';

        return [
            'name' => $name,
            'short_name' => Str::slug(fake()->unique()->word().'-'.fake()->randomNumber(4)),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'country' => fake()->countryCode(),
            'timezone' => 'UTC',
            'locale' => 'en',
            'is_active' => true,
        ];
    }
}
