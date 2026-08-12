<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/**
 * Shared by User (tenant) and PlatformUser (landlord) — the only two models
 * that authenticate in this app, on two separate guards. Mandatory MFA
 * (Phase 15) needs identical challenge/enrollment logic on both, and
 * LoginRequest/PlatformLoginRequest already don't share any code (they
 * query different connections on different guards), so this trait — not a
 * shared base class — is what keeps the columns/casts/helpers in one place.
 *
 * mfa_grace_period_ends_at starts at `now()` for brand-new accounts (set in
 * each model's own `creating` hook, alongside its existing `uuid ??= ...`
 * pattern) — a fresh signup is expected to set up MFA immediately, not get
 * a free pass. Existing accounts get backfilled to a real future date by
 * the Phase 15 rollout command so the "mandatory for everyone" enforcement
 * doesn't lock out every already-live user the moment this ships.
 */
trait HasTwoFactorAuthentication
{
    protected function twoFactorCasts(): array
    {
        return [
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'mfa_grace_period_ends_at' => 'datetime',
        ];
    }

    public function hasMfaConfirmed(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function isWithinMfaGracePeriod(): bool
    {
        return $this->mfa_grace_period_ends_at !== null && $this->mfa_grace_period_ends_at->isFuture();
    }

    public function mfaGracePeriodEndsAt(): ?Carbon
    {
        return $this->mfa_grace_period_ends_at;
    }
}
