import { httpClient } from '@/api/client'
import type { ApiResponse } from '@/types/api'
import type { SearchResponse } from '@/types/search'

export const searchApi = {
  search: async (query: string): Promise<SearchResponse> => {
    const { data } = await httpClient.get<ApiResponse<SearchResponse>>('/search', { params: { q: query } })
    return data.data
  },
}
