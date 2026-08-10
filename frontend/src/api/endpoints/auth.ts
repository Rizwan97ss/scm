import { ensureCsrfCookie, httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { LoginPayload, UpdatePasswordPayload, User } from '@/types/auth'
import type { SignupPayload, SignupResponse } from '@/types/signup'

export async function login(payload: LoginPayload): Promise<User> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<User>>('/auth/login', payload)
  return data.data
}

export async function signup(payload: SignupPayload): Promise<SignupResponse> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<SignupResponse>>('/auth/signup', payload)
  return data.data
}

export async function logout(): Promise<void> {
  await httpClient.post('/auth/logout')
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
