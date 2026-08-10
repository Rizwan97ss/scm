import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'

/**
 * Generates the standard index/show/store/update/destroy calls for a REST
 * resource. Covers every module whose backend controller extends
 * CrudController (academic structure, schools, etc.) — anything with extra
 * actions (promote, invite, ...) adds its own functions alongside this.
 */
export function createCrudEndpoints<TResource, TPayload = Partial<TResource>>(resourcePath: string) {
  return {
    list: async (params?: ListQueryParams): Promise<PaginatedResponse<TResource>> => {
      const { data } = await httpClient.get<PaginatedResponse<TResource>>(`/${resourcePath}`, { params })
      return data
    },
    get: async (id: number): Promise<TResource> => {
      const { data } = await httpClient.get<ApiResponse<TResource>>(`/${resourcePath}/${id}`)
      return data.data
    },
    create: async (payload: TPayload): Promise<TResource> => {
      const { data } = await httpClient.post<ApiResponse<TResource>>(`/${resourcePath}`, payload)
      return data.data
    },
    update: async (id: number, payload: Partial<TPayload>): Promise<TResource> => {
      const { data } = await httpClient.put<ApiResponse<TResource>>(`/${resourcePath}/${id}`, payload)
      return data.data
    },
    remove: async (id: number): Promise<void> => {
      await httpClient.delete(`/${resourcePath}/${id}`)
    },
  }
}
