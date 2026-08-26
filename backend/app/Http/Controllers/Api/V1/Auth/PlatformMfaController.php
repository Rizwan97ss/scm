<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionPasswordIsCurrent;
use App\Http\Resources\Platform\PlatformUserResource;
use App\Models\Platform\PlatformUser;
use App\Services\Mfa\MfaChallengeService;
use App\Services\Mfa\TwoFactorAuthenticationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Landlord-side twin of MfaController — same shape, 'platform' guard/PlatformUser. See MfaChallengeService's docblock for why these aren't merged. */
class PlatformMfaController extends Controller
{
    public function __construct(
        private readonly TwoFactorAuthenticationService $totp,
        private readonly MfaChallengeService $challenges,
    ) {}

    public function setup(Request $request): JsonResponse
    {
        $user = Auth::guard('platform')->user();
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

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = Auth::guard('platform')->user();

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

    public function verifyChallenge(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $result = $this->challenges->attempt(
            $request->string('challenge_token')->toString(),
            'platform',
            $request->string('code')->toString(),
            PlatformUser::class,
        );

        if (! $result) {
            throw ValidationException::withMessages(['code' => 'That code is incorrect, or the challenge has expired — please log in again.']);
        }

        Auth::guard('platform')->login($result['user'], $result['remember']);
        Auth::shouldUse('platform');
        $request->session()->regenerate();
        $request->session()->put(EnsureSessionPasswordIsCurrent::SESSION_TENANT_KEY, tenant()?->id);

        $result['user']->forceFill(['last_login_at' => now()])->saveQuietly();

        return ApiResponse::success(new PlatformUserResource($result['user']), 'Logged in successfully.');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = Auth::guard('platform')->user();

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
