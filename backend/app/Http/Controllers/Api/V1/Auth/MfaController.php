<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Mfa\MfaChallengeService;
use App\Services\Mfa\TwoFactorAuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-zone MFA enrollment + login-challenge completion. No permission
 * gate on any of these beyond auth:sanctum (or none, for verify-challenge)
 * — every role must be able to set up/use MFA on their own account, the
 * same self-service shape as PasswordController. See PlatformMfaController
 * for the landlord-side twin (same shape, different guard/model — see
 * MfaChallengeService's docblock for why the two aren't merged into one).
 */
class MfaController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $totp,
        private readonly MfaChallengeService $challenges,
    ) {}

    /** Generates a fresh, unconfirmed secret and returns the QR + manual key. Calling this again before confirming discards whatever secret was pending. */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totp->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return ApiResponse::success([
            'secret' => $secret,
            'qr_code' => $this->totp->qrCodeDataUri($this->totp->otpAuthUri($secret, $user->email)),
        ]);
    }

    /** Confirms the pending secret and returns the one-time recovery codes — never retrievable again after this response. */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages(['code' => 'Start MFA setup first.']);
        }

        if (! $this->totp->verifyCode($user->two_factor_secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect or has expired.']);
        }

        $recoveryCodes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->totp->hashRecoveryCodes($recoveryCodes),
            'mfa_grace_period_ends_at' => null,
        ])->save();

        return ApiResponse::success(['recovery_codes' => $recoveryCodes], 'Two-factor authentication enabled.');
    }

    /** The login flow's second step — guest route, no session yet. */
    public function verifyChallenge(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $result = $this->challenges->attempt(
            $request->string('challenge_token')->toString(),
            'web',
            $request->string('code')->toString(),
            User::class,
        );

        if (! $result) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect, or the challenge has expired — please log in again.']);
        }

        // Explicit guard, not the bare Auth::login() the original
        // (pre-MFA) LoginController used — by the time a second login
        // round-trip happens in the same process (e.g. log out, log back
        // in), Sanctum's own guard resolution has already touched the
        // Auth manager's default-guard caching, and a bare call can
        // resolve to Sanctum's RequestGuard (no login() method) instead
        // of the 'web' SessionGuard. PlatformMfaController's twin already
        // has to be explicit for the same reason (a non-default guard).
        Auth::guard('web')->login($result['user'], $result['remember']);
        $request->session()->regenerate();

        $result['user']->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::success(new UserResource($result['user']->load('roles')), 'Logged in successfully.');
    }

    /** Password-confirmed, not just session-authenticated — recovery codes are as sensitive as the password itself. */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        if (! $user->hasMfaConfirmed()) {
            throw ValidationException::withMessages(['password' => 'Two-factor authentication is not enabled on this account.']);
        }

        $recoveryCodes = $this->totp->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->totp->hashRecoveryCodes($recoveryCodes)])->save();

        return ApiResponse::success(['recovery_codes' => $recoveryCodes], 'Recovery codes regenerated — your old codes no longer work.');
    }
}
