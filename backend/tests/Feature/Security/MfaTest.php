<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\InteractsWithSchool;
use Tests\TestCase;

class MfaTest extends TestCase
{
    use InteractsWithSchool, RefreshDatabase;

    private function currentTotpCode(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    public function test_full_setup_confirm_and_login_challenge_round_trip(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', ['email' => 'teacher@mfa.test']);
        $this->actingAsInSchool($user);

        $setup = $this->postJson('/api/v1/auth/mfa/setup')->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertNotEmpty($secret);
        $this->assertStringStartsWith('data:image', $setup->json('data.qr_code'));

        $confirm = $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $this->currentTotpCode($secret)])->assertOk();
        $recoveryCodes = $confirm->json('data.recovery_codes');
        $this->assertCount(8, $recoveryCodes);

        $this->assertTrue($user->fresh()->hasMfaConfirmed());

        // Log out, then log back in — the second factor is now required.
        // forgetGuards() clears the Auth manager's cached guard instances:
        // actingAsInSchool() forced a user directly onto the 'web' guard
        // singleton this same test process reuses across every simulated
        // request, and a real /auth/logout doesn't reliably undo that
        // in-memory state on its own within one test method.
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->assertGuest();

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'teacher@mfa.test', 'password' => 'password'])->assertOk();
        $this->assertTrue($login->json('data.mfa_required'));
        $this->assertGuest();
        $challengeToken = $login->json('data.challenge_token');

        $bad = $this->postJson('/api/v1/auth/mfa/verify-challenge', ['challenge_token' => $challengeToken, 'code' => '000000']);
        $bad->assertStatus(422);
        $this->assertGuest();

        $good = $this->postJson('/api/v1/auth/mfa/verify-challenge', [
            'challenge_token' => $challengeToken,
            'code' => $this->currentTotpCode($secret),
        ])->assertOk();
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertEquals('teacher@mfa.test', $good->json('data.email'));
    }

    public function test_a_recovery_code_completes_the_challenge_and_is_then_consumed(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', ['email' => 'teacher2@mfa.test']);
        $this->actingAsInSchool($user);

        $secret = $this->postJson('/api/v1/auth/mfa/setup')->json('data.secret');
        $recoveryCodes = $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $this->currentTotpCode($secret)])
            ->json('data.recovery_codes');

        $this->postJson('/api/v1/auth/logout');
        $this->app['auth']->forgetGuards();
        $challengeToken = $this->postJson('/api/v1/auth/login', ['email' => 'teacher2@mfa.test', 'password' => 'password'])
            ->json('data.challenge_token');

        $firstCode = $recoveryCodes[0];
        $this->postJson('/api/v1/auth/mfa/verify-challenge', ['challenge_token' => $challengeToken, 'code' => $firstCode])
            ->assertOk();
        $this->assertAuthenticatedAs($user->fresh());

        // Same recovery code must not work a second time.
        $this->postJson('/api/v1/auth/logout');
        $this->app['auth']->forgetGuards();
        $challengeToken2 = $this->postJson('/api/v1/auth/login', ['email' => 'teacher2@mfa.test', 'password' => 'password'])
            ->json('data.challenge_token');
        $this->postJson('/api/v1/auth/mfa/verify-challenge', ['challenge_token' => $challengeToken2, 'code' => $firstCode])
            ->assertStatus(422);
        $this->assertGuest();
    }

    public function test_ensure_mfa_enrolled_blocks_non_exempt_routes_once_the_grace_period_ends_but_allows_exempt_ones(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', ['mfa_grace_period_ends_at' => now()->subDay()]);
        $this->actingAsInSchool($user);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertStatus(403)
            ->assertJsonPath('mfa_setup_required', true);

        // Exempt routes stay reachable so the account isn't a dead end.
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->postJson('/api/v1/auth/mfa/setup')->assertOk();
    }

    public function test_ensure_mfa_enrolled_allows_everything_during_the_grace_period(): void
    {
        $school = $this->createSchool();
        $user = $this->createUserWithRole($school, 'Teacher', ['mfa_grace_period_ends_at' => now()->addDays(5)]);
        $this->actingAsInSchool($user);

        $this->getJson('/api/v1/dashboard/summary')->assertOk();
    }

    public function test_admin_can_reset_a_users_mfa_but_only_with_the_manage_mfa_permission(): void
    {
        $school = $this->createSchool();
        $admin = $this->createUserWithRole($school, 'School Admin');
        $target = $this->createUserWithRole($school, 'Teacher');

        $this->actingAsInSchool($target);
        $secret = $this->postJson('/api/v1/auth/mfa/setup')->json('data.secret');
        $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $this->currentTotpCode($secret)]);
        $this->assertTrue($target->fresh()->hasMfaConfirmed());

        $this->actingAsInSchool($admin);
        $this->postJson("/api/v1/users/{$target->id}/mfa/reset")->assertOk();

        $target->refresh();
        $this->assertFalse($target->hasMfaConfirmed());
        $this->assertNull($target->two_factor_secret);
        $this->assertTrue($target->isWithinMfaGracePeriod());

        // A role without users.manage-mfa (e.g. a bare Teacher) is denied.
        $otherTeacher = $this->createUserWithRole($school, 'Teacher');
        $this->actingAsInSchool($otherTeacher);
        $this->postJson("/api/v1/users/{$target->id}/mfa/reset")->assertStatus(403);
    }

    public function test_platform_login_challenge_round_trip(): void
    {
        $platformUser = $this->createPlatformUser(['email' => 'super@platform.test']);
        $this->actingAsPlatform($platformUser);

        $secret = $this->postJson('/api/v1/auth/platform-mfa/setup')->json('data.secret');
        $this->postJson('/api/v1/auth/platform-mfa/confirm', ['code' => $this->currentTotpCode($secret)])->assertOk();

        $this->postJson('/api/v1/auth/platform-logout');
        $this->app['auth']->forgetGuards();
        $this->assertGuest('platform');

        $login = $this->postJson('/api/v1/auth/platform-login', ['email' => 'super@platform.test', 'password' => 'password'])
            ->assertOk();
        $this->assertTrue($login->json('data.mfa_required'));
        $challengeToken = $login->json('data.challenge_token');

        $this->postJson('/api/v1/auth/platform-mfa/verify-challenge', [
            'challenge_token' => $challengeToken,
            'code' => $this->currentTotpCode($secret),
        ])->assertOk();
        $this->assertAuthenticatedAs($platformUser->fresh(), 'platform');
    }
}
