import { ensureCsrfCookie, httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { PlatformLoginPayload, PlatformUser } from '@/types/platform'

export async function platformLogin(payload: PlatformLoginPayload): Promise<PlatformUser> {
  await ensureCsrfCookie()
  const { data } = await httpClient.post<ApiResponse<PlatformUser>>('/auth/platform-login', payload)
  return data.data
}

export async function platformLogout(): Promise<void> {
  await httpClient.post('/auth/platform-logout')
}

export async function fetchPlatformMe(): Promise<PlatformUser> {
  const { data } = await httpClient.get<ApiResponse<PlatformUser>>('/auth/platform-me')
  return data.data
}
