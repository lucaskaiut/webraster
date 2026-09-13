import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Contract } from '@/shared/types/models'

export type ContractListParams = ListParams

export interface ContractPayload {
  name: string
  body: string
}

export const contractsService = {
  async list(params: ContractListParams): Promise<PaginatedResponse<Contract>> {
    const response = await http.get<PaginatedResponse<Contract>>('/contracts', { params })

    return response.data
  },

  async get(id: string): Promise<Contract> {
    const response = await http.get<ApiResponse<Contract>>(`/contracts/${id}`)

    return response.data.data
  },

  async create(payload: ContractPayload): Promise<Contract> {
    const response = await http.post<ApiResponse<Contract>>('/contracts', payload)

    return response.data.data
  },

  async update(id: string, payload: ContractPayload): Promise<Contract> {
    const response = await http.put<ApiResponse<Contract>>(`/contracts/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/contracts/${id}`)
  },
}
