/** Shared shape of the two fields both User and PlatformUser carry (see their resources' mfa_enabled/mfa_grace_period_ends_at). */
interface MfaStatus {
  mfa_enabled: boolean
  mfa_grace_period_ends_at: string | null
}

/** True once the backend's EnsureMfaEnrolled would 403 this account on any non-exempt route — mirrors its own grace-period logic. */
export function needsMfaSetup(user: MfaStatus | null | undefined): boolean {
  if (!user || user.mfa_enabled) return false
  if (!user.mfa_grace_period_ends_at) return true
  return new Date(user.mfa_grace_period_ends_at).getTime() <= Date.now()
}

/** True while a nudge banner should show but the account isn't locked out yet. */
export function isInMfaGracePeriod(user: MfaStatus | null | undefined): boolean {
  if (!user || user.mfa_enabled || !user.mfa_grace_period_ends_at) return false
  return new Date(user.mfa_grace_period_ends_at).getTime() > Date.now()
}
