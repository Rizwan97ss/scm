import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { Plan } from '@/types/plan'

export const plansApi = {
  list: async (): Promise<Plan[]> => {
    const { data } = await httpClient.get<ApiResponse<Plan[]>>('/plans')
    return data.data
  },
}
