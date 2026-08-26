import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import type { ApiResponse, ListQueryParams, PaginatedResponse } from '@/types/api'
import type { RouteStop, RouteStopPayload, RoutePayload, StudentTransportAssignment, StudentTransportAssignmentPayload, TransportRoute, Vehicle, VehiclePayload } from '@/types/transport'

export const vehiclesApi = createCrudEndpoints<Vehicle, VehiclePayload>('vehicles')

export const routesApi = {
  ...createCrudEndpoints<TransportRoute, RoutePayload>('routes'),
  addStop: async (routeId: number, payload: RouteStopPayload): Promise<RouteStop> => {
    const { data } = await httpClient.post<ApiResponse<RouteStop>>(`/routes/${routeId}/stops`, payload)
    return data.data
  },
  removeStop: async (routeId: number, stopId: number): Promise<void> => {
    await httpClient.delete(`/routes/${routeId}/stops/${stopId}`)
  },
}

export const studentTransportAssignmentsApi = {
  list: async (params?: ListQueryParams): Promise<PaginatedResponse<StudentTransportAssignment>> => {
    const { data } = await httpClient.get<PaginatedResponse<StudentTransportAssignment>>('/student-transport-assignments', { params })
    return data
  },
  assign: async (payload: StudentTransportAssignmentPayload): Promise<StudentTransportAssignment> => {
    const { data } = await httpClient.post<ApiResponse<StudentTransportAssignment>>('/student-transport-assignments', payload)
    return data.data
  },
}
