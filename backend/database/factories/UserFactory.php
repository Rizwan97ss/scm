<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * Creates a row in whichever tenant connection is currently active
 * (tenancy()->initialize($school)) — there is no school_id column or
 * relation to stamp, physical database separation is what scopes the user.
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Real accounts get zero grace (see User::booted()'s `creating`
            // hook) — a factory-created test user isn't a real new signup,
            // and EnsureMfaEnrolled would otherwise 403 nearly every
            // authenticated feature test. A test exercising MFA enforcement
            // itself overrides this explicitly via ->state(...).
            'mfa_grace_period_ends_at' => now()->addYears(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
