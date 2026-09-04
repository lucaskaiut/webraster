import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Client, User } from '@/shared/types/models'

export interface ClientPayload {
  name: string
  document: string
  email?: string | null
  phone?: string | null
  street?: string | null
  number?: string | null
  complement?: string | null
  neighborhood?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
  is_active?: boolean
}

export interface ClientUserPayload {
  name: string
  email: string
  phone?: string | null
  document?: string | null
  password: string
  role_ids?: number[]
}

export const clientsService = {
  async list(params: ListParams): Promise<PaginatedResponse<Client>> {
    const response = await http.get<PaginatedResponse<Client>>('/clients', { params })

    return response.data
  },

  async get(id: string): Promise<Client> {
    const response = await http.get<ApiResponse<Client>>(`/clients/${id}`)

    return response.data.data
  },

  async create(payload: ClientPayload): Promise<Client> {
    const response = await http.post<ApiResponse<Client>>('/clients', payload)

    return response.data.data
  },

  async update(id: string, payload: ClientPayload): Promise<Client> {
    const response = await http.put<ApiResponse<Client>>(`/clients/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/clients/${id}`)
  },

  async listUsers(clientId: string, params: ListParams): Promise<PaginatedResponse<User>> {
    const response = await http.get<PaginatedResponse<User>>(`/clients/${clientId}/users`, {
      params,
    })

    return response.data
  },

  async createUser(clientId: string, payload: ClientUserPayload): Promise<User> {
    const response = await http.post<ApiResponse<User>>(`/clients/${clientId}/users`, payload)

    return response.data.data
  },

  async removeUser(clientId: string, userId: string): Promise<void> {
    await http.delete(`/clients/${clientId}/users/${userId}`)
  },
}
