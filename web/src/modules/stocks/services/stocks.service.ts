import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Stock } from '@/shared/types/models'

export interface StockListParams extends Omit<ListParams, 'per_page'> {
  per_page?: number | 'all'
  is_active?: boolean
  sort?: string
}

export interface StockPayload {
  name?: string
  description?: string | null
  is_active?: boolean
}

export const stocksService = {
  async list(params: StockListParams): Promise<PaginatedResponse<Stock>> {
    const response = await http.get<PaginatedResponse<Stock>>('/stocks', { params })

    return response.data
  },

  async get(id: string): Promise<Stock> {
    const response = await http.get<ApiResponse<Stock>>(`/stocks/${id}`)

    return response.data.data
  },

  async create(payload: StockPayload): Promise<Stock> {
    const response = await http.post<ApiResponse<Stock>>('/stocks', payload)

    return response.data.data
  },

  async update(id: string, payload: StockPayload): Promise<Stock> {
    const response = await http.put<ApiResponse<Stock>>(`/stocks/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/stocks/${id}`)
  },
}
