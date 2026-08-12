import { ensureCsrfCookie, httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { MfaChallengePayload, MfaRecoveryCodesResponse, MfaSetupResponse } from '@/types/auth'
import type { PlatformLoginPayload, PlatformLoginResult, PlatformUser } from '@/types/platform'

export async function platformLogin(payload: PlatformLoginPayload): Promise<PlatformLoginResult> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<{ mfa_required: boolean; challenge_token?: string } | PlatformUser>>(
    '/auth/platform-login',
    payload
  )
  const body = data.data as { mfa_required?: boolean; challenge_token?: string }
  if (body.mfa_required) {
    return { mfa_required: true, challenge_token: body.challenge_token! }
  }
  return { mfa_required: false, user: data.data as PlatformUser }
}

export async function verifyPlatformMfaChallenge(payload: MfaChallengePayload): Promise<PlatformUser> {
  const { data } = await httpClient.post<ApiResponse<PlatformUser>>('/auth/platform-mfa/verify-challenge', payload)
  return data.data
}

export async function setupPlatformMfa(): Promise<MfaSetupResponse> {
  const { data } = await httpClient.post<ApiResponse<MfaSetupResponse>>('/auth/platform-mfa/setup')
  return data.data
}

export async function confirmPlatformMfa(code: string): Promise<MfaRecoveryCodesResponse> {
  const { data } = await httpClient.post<ApiResponse<MfaRecoveryCodesResponse>>('/auth/platform-mfa/confirm', { code })
  return data.data
}

export async function regeneratePlatformMfaRecoveryCodes(password: string): Promise<MfaRecoveryCodesResponse> {
  const { data } = await httpClient.post<ApiResponse<MfaRecoveryCodesResponse>>('/auth/platform-mfa/recovery-codes/regenerate', { password })
  return data.data
}

export async function platformLogout(): Promise<void> {
  await httpClient.post('/auth/platform-logout')
}

export async function fetchPlatformMe(): Promise<PlatformUser> {
  const { data } = await httpClient.get<ApiResponse<PlatformUser>>('/auth/platform-me')
  return data.data
}
