<?php

namespace App\Services\Mfa;

use App\Support\QrCodeGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Pure TOTP mechanics — secret generation, QR rendering, code verification,
 * recovery codes. No persistence and no knowledge of which model/guard is
 * calling it; that's MfaChallengeService's job. Kept separate so both the
 * enrollment endpoints (this class) and the login-challenge endpoints
 * (MfaChallengeService, which composes this one) stay single-purpose.
 */
class TwoFactorAuthenticationService
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** otpauth:// URI an authenticator app scans — rendered as a QR by qrCodeDataUri() below. */
    public function otpAuthUri(string $secret, string $holderEmail): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('app.name'), $holderEmail, $secret);
    }

    public function qrCodeDataUri(string $otpAuthUri): string
    {
        return QrCodeGenerator::dataUri($otpAuthUri, 200);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code) !== false;
    }

    /** @return array<int, string> 8 plaintext codes — shown to the user exactly once by the caller, never stored or re-shown in this form. */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn () => Str::random(4).'-'.Str::random(4).'-'.Str::random(4))
            ->all();
    }

    /** @param  array<int, string>  $plaintextCodes
     * @return array<int, string> bcrypt hashes, what actually gets stored in two_factor_recovery_codes */
    public function hashRecoveryCodes(array $plaintextCodes): array
    {
        return array_map(fn (string $code) => Hash::make($code), $plaintextCodes);
    }

    /**
     * Checks a submitted recovery code against the stored hashes and, if it
     * matches, returns the remaining hash list with that one removed — a
     * recovery code is single-use, the caller is responsible for persisting
     * the returned (shorter) array back onto the model.
     *
     * @param  array<int, string>|null  $hashedCodes
     * @return array{matched: bool, remaining: array<int, string>}
     */
    public function consumeRecoveryCode(?array $hashedCodes, string $submittedCode): array
    {
        $hashedCodes ??= [];

        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($submittedCode, $hash)) {
                $remaining = $hashedCodes;
                unset($remaining[$index]);

                return ['matched' => true, 'remaining' => array_values($remaining)];
            }
        }

        return ['matched' => false, 'remaining' => $hashedCodes];
    }
}
