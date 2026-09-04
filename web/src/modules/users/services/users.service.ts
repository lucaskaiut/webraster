import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { User } from '@/shared/types/models'

export interface UserPayload {
  name: string
  email: string
  phone?: string | null
  document?: string | null
  password?: string
  role_ids: number[]
}

export const usersService = {
  async list(params: ListParams): Promise<PaginatedResponse<User>> {
    const response = await http.get<PaginatedResponse<User>>('/users', { params })

    return response.data
  },

  async get(id: string): Promise<User> {
    const response = await http.get<ApiResponse<User>>(`/users/${id}`)

    return response.data.data
  },

  async create(payload: UserPayload): Promise<User> {
    const response = await http.post<ApiResponse<User>>('/users', payload)

    return response.data.data
  },

  async update(id: string, payload: UserPayload): Promise<User> {
    const response = await http.put<ApiResponse<User>>(`/users/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/users/${id}`)
  },
}
