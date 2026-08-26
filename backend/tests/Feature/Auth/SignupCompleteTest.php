<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * The other half of the signup handoff (see SignupController/
 * SignupCompleteController docblocks) — a session created on the central
 * signup domain can't carry over to the new tenant's own subdomain, so
 * provision() hands back a one-time password-reset-broker token embedded
 * in the Stripe success_url instead. This is what actually establishes the
 * session, on the tenant's own origin.
 */
class SignupCompleteTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_valid_token_logs_the_admin_in_and_consumes_the_token(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin', ['email' => 'admin@new-horizon.test']);
        $token = Password::broker()->createToken($admin);

        $response = $this->postJson('/api/v1/auth/signup/complete', [
            'email' => 'admin@new-horizon.test',
            'token' => $token,
        ]);

        $response->assertOk()->assertJsonPath('data.email', 'admin@new-horizon.test');
        $this->assertAuthenticatedAs($admin->fresh());
        $this->assertNotNull($admin->fresh()->last_login_at);

        // One-time: the same token must not work a second time.
        $this->postJson('/api/v1/auth/signup/complete', [
            'email' => 'admin@new-horizon.test',
            'token' => $token,
        ])->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'School Admin', ['email' => 'admin@new-horizon.test']);

        $response = $this->postJson('/api/v1/auth/signup/complete', [
            'email' => 'admin@new-horizon.test',
            'token' => 'not-a-real-token',
        ]);

        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected(): void
    {
        $school = $this->createSchool();

        $response = $this->postJson('/api/v1/auth/signup/complete', [
            'email' => 'nobody@new-horizon.test',
            'token' => 'irrelevant',
        ]);

        $response->assertStatus(401);
        $this->assertGuest();
    }
}
