import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { SettingsMap, UpdateSettingsPayload } from '@/types/settings'

export async function fetchSettings(): Promise<SettingsMap> {
  const { data } = await httpClient.get<ApiResponse<SettingsMap>>('/settings')
  return data.data
}

export async function fetchPublicSettings(school?: string): Promise<SettingsMap> {
  const { data } = await httpClient.get<ApiResponse<SettingsMap>>('/settings/public', {
    params: school ? { school } : undefined,
  })
  return data.data
}

export async function updateSettings(payload: UpdateSettingsPayload): Promise<SettingsMap> {
  const { data } = await httpClient.put<ApiResponse<SettingsMap>>('/settings', payload)
  return data.data
}
