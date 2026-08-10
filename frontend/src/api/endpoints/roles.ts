import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'

export interface Role {
  id: number
  name: string
  is_system: boolean
  permissions: string[]
}

export interface RolePayload {
  name: string
  permissions?: string[]
}

export type PermissionsByModule = Record<string, { name: string; action: string }[]>

export const rolesApi = {
  list: async (): Promise<Role[]> => {
    const { data } = await httpClient.get<ApiResponse<Role[]>>('/roles')
    return data.data
  },
  get: async (id: number): Promise<Role> => {
    const { data } = await httpClient.get<ApiResponse<Role>>(`/roles/${id}`)
    return data.data
  },
  create: async (payload: RolePayload): Promise<Role> => {
    const { data } = await httpClient.post<ApiResponse<Role>>('/roles', payload)
    return data.data
  },
  update: async (id: number, payload: Partial<RolePayload>): Promise<Role> => {
    const { data } = await httpClient.put<ApiResponse<Role>>(`/roles/${id}`, payload)
    return data.data
  },
  remove: async (id: number): Promise<void> => {
    await httpClient.delete(`/roles/${id}`)
  },
}

export const permissionsApi = {
  list: async (): Promise<PermissionsByModule> => {
    const { data } = await httpClient.get<ApiResponse<PermissionsByModule>>('/permissions')
    return data.data
  },
}
