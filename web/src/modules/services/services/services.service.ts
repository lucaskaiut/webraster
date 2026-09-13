import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { CatalogService } from '@/shared/types/models'

export type ServiceListParams = ListParams

export interface ServicePayload {
  name: string
  amount_cents: number
}

export const servicesService = {
  async list(params: ServiceListParams): Promise<PaginatedResponse<CatalogService>> {
    const response = await http.get<PaginatedResponse<CatalogService>>('/services', { params })

    return response.data
  },

  async get(id: string): Promise<CatalogService> {
    const response = await http.get<ApiResponse<CatalogService>>(`/services/${id}`)

    return response.data.data
  },

  async create(payload: ServicePayload): Promise<CatalogService> {
    const response = await http.post<ApiResponse<CatalogService>>('/services', payload)

    return response.data.data
  },

  async update(id: string, payload: ServicePayload): Promise<CatalogService> {
    const response = await http.put<ApiResponse<CatalogService>>(`/services/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/services/${id}`)
  },
}
