<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

/**
 * Before EnsureSessionPasswordIsCurrent and EnsureUserIsActive (see
 * routes/api.php and bootstrap/app.php), nothing re-checked a session's
 * validity after the moment it was created — a password change (self-service
 * or admin-triggered) or an admin suspending the account left every other
 * already-authenticated session/tab fully usable indefinitely.
 *
 * These tests use a real login (postJson) rather than actingAsInSchool()
 * wherever session continuity across two separate requests actually matters:
 * actingAsInSchool() injects a PHP object straight onto the guard for each
 * call, bypassing the real cookie-backed session entirely, so it can't prove
 * a session persists (or gets killed) across two requests the way a real
 * browser tab would experience it.
 */
class SessionInvalidationTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    public function test_a_session_is_invalidated_once_the_users_password_changes_out_of_band(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'teacher@example.com', 'password' => 'correct-password'])
            ->assertOk();

        // First authenticated request under EnsureSessionPasswordIsCurrent
        // stores the CURRENT password hash into this real session.
        $this->getJson('/api/v1/auth/me')->assertOk();

        // Simulated "changed via a different device/session" -- not through
        // this session at all, so its stored hash is now stale.
        $user->forceFill(['password' => Hash::make('changed-elsewhere-123')])->save();

        // forgetGuards() clears the Auth manager's cached guard/user
        // instance -- PHPUnit runs the whole test in one PHP process, so
        // without this the next simulated request would reuse the
        // already-resolved (pre-change) user rather than re-authenticating
        // via the session the way a real, separately-processed HTTP request
        // would. Same reasoning as MfaTest's own use of this after logout.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_session_survives_the_users_own_password_change_through_it(): void
    {
        $school = $this->createSchool();
        $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher2@example.com',
            'password' => Hash::make('current-password-1'),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'teacher2@example.com', 'password' => 'current-password-1'])
            ->assertOk();

        $this->getJson('/api/v1/auth/me')->assertOk();

        // EnsureSessionPasswordIsCurrent re-stashes the new hash into the
        // SAME session after a successful response -- changing your own
        // password through your own session must not log that session out.
        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'current-password-1',
            'password' => 'NewPassw0rd!123',
            'password_confirmation' => 'NewPassw0rd!123',
        ])->assertOk();

        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_a_session_is_invalidated_the_moment_the_user_is_suspended(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', [
            'email' => 'teacher3@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'teacher3@example.com', 'password' => 'correct-password'])
            ->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk();

        // Out-of-band, same idea as the password case above -- an admin's
        // own UserController::updateStatus request is a separate,
        // independently-authenticated call; mutating the row directly here
        // isolates what's actually under test (EnsureUserIsActive re-checking
        // status on the VICTIM's session) from that unrelated endpoint,
        // which already has its own coverage (test_a_suspended_user_cannot_
        // reactivate_themselves below, and UserCrudTest).
        $user->forceFill(['status' => UserStatus::Suspended])->save();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_a_suspended_user_cannot_reactivate_themselves(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', ['status' => 'suspended']);

        $response = $this->actingAsInSchool($user)
            ->postJson("/api/v1/users/{$user->id}/status", ['status' => 'active']);

        // EnsureUserIsActive rejects the request outright before it ever
        // reaches UserPolicy::updateStatus -- a suspended user's session is
        // dead on arrival for every route, this one included.
        $response->assertStatus(401);
    }
}
