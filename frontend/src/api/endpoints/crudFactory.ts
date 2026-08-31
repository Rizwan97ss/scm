import { httpClient } from '@/api/client'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { ImportMode, ImportResult } from '@/types/import'

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

/**
 * The template-download/import pair every LookupImportController-backed
 * resource exposes (departments, grade levels, sections, subjects, rooms)
 * — same rationale as createCrudEndpoints, one factory instead of
 * retyping the same two functions per resource. These are also the only
 * imports that support update/upsert mode (see LookupImportController) —
 * students/staff imports don't use this, since 'create' is the only thing
 * they support.
 */
export function createImportEndpoints(resourcePath: string) {
  return {
    importTemplateUrl: `/${resourcePath}/import/template`,
    import: async (file: File, dryRun = false, mode: ImportMode = 'create'): Promise<ImportResult> => {
      const formData = new FormData()
      formData.append('file', file)
      if (dryRun) formData.append('dry_run', '1')
      formData.append('mode', mode)
      const { data } = await httpClient.post<ApiResponse<ImportResult>>(`/${resourcePath}/import`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return data.data
    },
  }
}
