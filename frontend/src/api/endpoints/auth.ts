import { ensureCsrfCookie, httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type {
  LoginPayload,
  LoginResult,
  MfaChallengePayload,
  MfaRecoveryCodesResponse,
  MfaSetupResponse,
  UpdatePasswordPayload,
  User,
} from '@/types/auth'
import type { SignupCompletePayload, SignupPayload, SignupResponse } from '@/types/signup'

/** data.mfa_required distinguishes a real login from a second-step challenge — see LoginRequest::authenticate()'s docblock. */
export async function login(payload: LoginPayload): Promise<LoginResult> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<{ mfa_required: boolean; challenge_token?: string } | User>>('/auth/login', payload)
  const body = data.data as { mfa_required?: boolean; challenge_token?: string }
  if (body.mfa_required) {
    return { mfa_required: true, challenge_token: body.challenge_token! }
  }
  return { mfa_required: false, user: data.data as User }
}

export async function verifyMfaChallenge(payload: MfaChallengePayload): Promise<User> {
  const { data } = await httpClient.post<ApiResponse<User>>('/auth/mfa/verify-challenge', payload)
  return data.data
}

export async function setupMfa(): Promise<MfaSetupResponse> {
  const { data } = await httpClient.post<ApiResponse<MfaSetupResponse>>('/auth/mfa/setup')
  return data.data
}

export async function confirmMfa(code: string): Promise<MfaRecoveryCodesResponse> {
  const { data } = await httpClient.post<ApiResponse<MfaRecoveryCodesResponse>>('/auth/mfa/confirm', { code })
  return data.data
}

export async function regenerateMfaRecoveryCodes(password: string): Promise<MfaRecoveryCodesResponse> {
  const { data } = await httpClient.post<ApiResponse<MfaRecoveryCodesResponse>>('/auth/mfa/recovery-codes/regenerate', { password })
  return data.data
}

export async function signup(payload: SignupPayload): Promise<SignupResponse> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<SignupResponse>>('/auth/signup', payload)
  return data.data
}

/** The tenant-subdomain half of the signup handoff — see SignupCompletePage. */
export async function signupComplete(payload: SignupCompletePayload): Promise<User> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<User>>('/auth/signup/complete', payload)
  return data.data
}

export async function logout(): Promise<void> {
  await httpClient.post('/auth/logout')
}

export async function deleteAccount(): Promise<void> {
  await httpClient.delete('/account')
}

export async function fetchMe(): Promise<User> {
  const { data } = await httpClient.get<ApiResponse<User>>('/auth/me')
  return data.data
}

export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
  await httpClient.put('/auth/password', payload)
}

export async function forgotPassword(email: string): Promise<void> {
  await httpClient.post('/auth/forgot-password', { email })
}

export async function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}): Promise<void> {
  await httpClient.post('/auth/reset-password', payload)
}
