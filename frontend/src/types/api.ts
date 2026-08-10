/** Matches App\Support\ApiResponse on the backend. */
export interface ApiResponse<T> {
  success: boolean
  message: string | null
  data: T
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: PaginationMeta
}

export interface ApiErrorResponse {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface ListQueryParams {
  page?: number
  per_page?: number
  sort?: string
  filter?: Record<string, string | number | boolean>
  [key: string]: unknown
}
