import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { PlatformSchool } from '@/types/platform'

/** BillingController::show() returns the exact same shape as PlatformSchoolResource, scoped to the caller's own school. */
export const billingApi = {
  show: async (): Promise<PlatformSchool> => {
    const { data } = await httpClient.get<ApiResponse<PlatformSchool>>('/billing')
    return data.data
  },
  portal: async (): Promise<string> => {
    const { data } = await httpClient.get<ApiResponse<{ url: string }>>('/billing/portal')
    return data.data.url
  },
}
