export interface ApiResponse<T> {
  success: boolean
  message: string | null
  data: T
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> {
  success: boolean
  message: string | null
  data: T[]
  meta: PaginationMeta
}

export interface ListParams {
  page?: number
  per_page?: number
  search?: string
}
