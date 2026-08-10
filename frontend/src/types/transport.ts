export interface Vehicle {
  id: number
  registration_number: string
  capacity: number
  driver_name: string | null
  driver_phone: string | null
  is_active: boolean
  created_at: string
}

export interface VehiclePayload {
  registration_number: string
  capacity: number
  driver_name?: string | null
  driver_phone?: string | null
  is_active?: boolean
}

export interface RouteStop {
  id: number
  route_id: number
  name: string
  sequence: number
}

export interface RouteStopPayload {
  name: string
  sequence?: number
}

export interface TransportRoute {
  id: number
  name: string
  description: string | null
  is_active: boolean
  stops: RouteStop[]
  created_at: string
}

export interface RoutePayload {
  name: string
  description?: string | null
  is_active?: boolean
  stops?: RouteStopPayload[]
}

export interface StudentTransportAssignment {
  id: number
  student?: { id: number; full_name: string }
  route?: { id: number; name: string }
  route_stop?: { id: number; name: string }
  vehicle?: { id: number; registration_number: string } | null
  effective_from: string
  is_active: boolean
  created_at: string
}

export interface StudentTransportAssignmentPayload {
  student_id: number
  route_id: number
  route_stop_id: number
  vehicle_id?: number | null
  effective_from: string
}
