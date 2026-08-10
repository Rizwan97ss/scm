import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { Hostel, HostelAllocation, HostelAllocationPayload, HostelPayload, HostelRoom, HostelRoomPayload } from '@/types/hostel'

export const hostelsApi = createCrudEndpoints<Hostel, HostelPayload>('hostels')

export const hostelRoomsApi = createCrudEndpoints<HostelRoom, HostelRoomPayload>('hostel-rooms')

export const hostelAllocationsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<HostelAllocation>> => {
    const { data } = await httpClient.get<PaginatedResponse<HostelAllocation>>('/hostel-allocations', { params })
    return data
  },
  allocate: async (payload: HostelAllocationPayload): Promise<HostelAllocation> => {
    const { data } = await httpClient.post<ApiResponse<HostelAllocation>>('/hostel-allocations', payload)
    return data.data
  },
  vacate: async (id: number): Promise<HostelAllocation> => {
    const { data } = await httpClient.post<ApiResponse<HostelAllocation>>(`/hostel-allocations/${id}/vacate`)
    return data.data
  },
}
