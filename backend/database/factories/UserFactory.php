<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * TODO(tenancy): 'school_id' below is a straightforwardly broken reference
 * -- the users table no longer has that column (Sub-phase D). Needs
 * Sub-phase F's test-infrastructure rewrite: creating a User now means
 * creating it inside a tenant's own database connection
 * (tenancy()->initialize($school) / $school->run(...)), not stamping a
 * foreign key. Left as a known-broken placeholder rather than patched
 * piecemeal ahead of that rewrite.
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_id' => null,
        ]);
    }
}
