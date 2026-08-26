<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * The forgot/reset-password routes (routes/api.php) existed with zero test
 * coverage before this — a live, throttled, unauthenticated endpoint that
 * mutates a user's password based on a token is exactly the kind of surface
 * that needs its own tests, not just an assumption that Laravel's Password
 * broker "just works" out of the box.
 */
class PasswordResetTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_forgot_password_responds_generically_for_existing_and_unknown_email(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', ['email' => 'known@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.com']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        // Same status and message shape either way -- distinguishing them
        // would let an attacker enumerate which emails have accounts.
        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }

    public function test_valid_token_resets_password_and_allows_login_with_new_password(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', [
            'email' => 'reset@example.com',
            'password' => Hash::make('old-password-1'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'NewPassw0rd!123',
            'password_confirmation' => 'NewPassw0rd!123',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'NewPassw0rd!123',
        ]);
        $login->assertOk();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', [
            'email' => 'reset2@example.com',
            'password' => Hash::make('old-password-1'),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-real-token',
            'email' => 'reset2@example.com',
            'password' => 'NewPassw0rd!123',
            'password_confirmation' => 'NewPassw0rd!123',
        ]);

        $response->assertStatus(422);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'reset2@example.com',
            'password' => 'old-password-1',
        ]);
        $login->assertOk();
    }

    /**
     * Laravel's PasswordBroker invalidates a token the moment it's
     * successfully consumed -- replaying the same token (e.g. an attacker
     * who intercepted the reset email link after the real user already used
     * it) must fail, not silently reset the password a second time.
     */
    public function test_token_cannot_be_reused_after_a_successful_reset(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', [
            'email' => 'reset3@example.com',
            'password' => Hash::make('old-password-1'),
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset3@example.com',
            'password' => 'NewPassw0rd!123',
            'password_confirmation' => 'NewPassw0rd!123',
        ])->assertOk();

        $replay = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset3@example.com',
            'password' => 'AnotherPassw0rd!456',
            'password_confirmation' => 'AnotherPassw0rd!456',
        ]);

        $replay->assertStatus(422);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', ['email' => 'throttle@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'throttle@example.com']);
        }

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'throttle@example.com']);

        $response->assertStatus(429);
    }
}
