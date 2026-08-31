export type SystemHealthStatus = 'ok' | 'warning' | 'error'

export interface SystemHealthCheck {
  key: string
  label: string
  status: SystemHealthStatus
  message: string
}

export interface SystemHealth {
  checks: SystemHealthCheck[]
  completion_percentage: number
}
