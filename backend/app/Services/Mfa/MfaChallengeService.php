<?php

namespace App\Services\Mfa;

use App\Models\Concerns\HasTwoFactorAuthentication;
use App\Models\Platform\PlatformUser;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The second-step login flow: once LoginRequest/PlatformLoginRequest verify
 * a password but find MFA already confirmed on the account, they issue a
 * challenge here instead of calling Auth::login() directly. The opaque
 * token is cache-backed (not session-based) — a two-step login shouldn't
 * depend on a cookie surviving between the two requests the way a session
 * would require.
 *
 * Deliberately guard-scoped rather than a single shared verify-challenge
 * endpoint: LoginController's tenant flow and PlatformLoginController's
 * platform flow each call this with their own guard, are given back a
 * guard-tagged token, and reject at consume time if the two don't match —
 * a platform-issued challenge can never complete a tenant login or vice
 * versa, even if the token were somehow guessed.
 */
class MfaChallengeService
{
    private const TTL_MINUTES = 5;

    public function __construct(private readonly TwoFactorAuthenticationService $totp) {}

    /** @param  User|PlatformUser  $user */
    public function issueChallenge(Authenticatable $user, string $guard, bool $remember): string
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'guard' => $guard,
            'user_id' => $user->getKey(),
            'remember' => $remember,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * Resolves the challenged user for the given guard/code, WITHOUT
     * consuming the token — the caller (LoginController/PlatformLoginController)
     * still needs to call Auth::login() itself before the token is spent,
     * so a code that verifies but a subsequent step fails doesn't burn the
     * user's one attempt for nothing.
     *
     * @param  class-string<User|PlatformUser>  $modelClass  The caller's own guard's model — this class doesn't hardcode either one.
     * @return array{user: Authenticatable, remember: bool}|null
     */
    public function attempt(string $token, string $guard, string $code, string $modelClass): ?array
    {
        $payload = Cache::get($this->cacheKey($token));

        if (! $payload || $payload['guard'] !== $guard) {
            return null;
        }

        /** @var (Authenticatable&HasTwoFactorAuthentication)|null $user */
        $user = $modelClass::query()->find($payload['user_id']);

        if (! $user || ! $user->hasMfaConfirmed()) {
            return null;
        }

        if ($this->totp->verifyCode($user->two_factor_secret, $code)) {
            Cache::forget($this->cacheKey($token));

            return ['user' => $user, 'remember' => $payload['remember']];
        }

        $result = $this->totp->consumeRecoveryCode($user->two_factor_recovery_codes, $code);

        if ($result['matched']) {
            $user->forceFill(['two_factor_recovery_codes' => $result['remaining']])->saveQuietly();
            Cache::forget($this->cacheKey($token));

            return ['user' => $user, 'remember' => $payload['remember']];
        }

        return null;
    }

    private function cacheKey(string $token): string
    {
        return "mfa_challenge:{$token}";
    }
}
