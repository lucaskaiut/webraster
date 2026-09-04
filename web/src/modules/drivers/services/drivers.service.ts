import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Driver } from '@/shared/types/models'

export interface DriverListParams extends ListParams {
  client_id?: string
}

export interface DriverPayload {
  client_id: string
  name: string
  document?: string | null
  phone?: string | null
  email?: string | null
  cnh_number?: string | null
  cnh_expires_at?: string | null
  notes?: string | null
  is_active?: boolean
}

export const driversService = {
  async list(params: DriverListParams): Promise<PaginatedResponse<Driver>> {
    const response = await http.get<PaginatedResponse<Driver>>('/drivers', { params })

    return response.data
  },

  async get(id: string): Promise<Driver> {
    const response = await http.get<ApiResponse<Driver>>(`/drivers/${id}`)

    return response.data.data
  },

  async create(payload: DriverPayload): Promise<Driver> {
    const response = await http.post<ApiResponse<Driver>>('/drivers', payload)

    return response.data.data
  },

  async update(id: string, payload: DriverPayload): Promise<Driver> {
    const response = await http.put<ApiResponse<Driver>>(`/drivers/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/drivers/${id}`)
  },
}
