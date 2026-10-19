import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { User, UserType, UserWorkHour } from '@/shared/types/models'

export interface UserListParams extends ListParams {
  type?: Exclude<UserType, 'client'>
  technician_active?: boolean
}

export interface UserPayload {
  name: string
  email: string
  phone?: string | null
  document?: string | null
  password?: string
  type: Exclude<UserType, 'client'>
  role_ids: number[]
  technician_active?: boolean
  zip?: string | null
  street?: string | null
  number?: string | null
  complement?: string | null
  neighborhood?: string | null
  city?: string | null
  state?: string | null
  work_hours?: UserWorkHour[]
}

export const usersService = {
  async list(params: UserListParams): Promise<PaginatedResponse<User>> {
    const response = await http.get<PaginatedResponse<User>>('/users', {
      params: {
        ...params,
        technician_active:
          params.technician_active === undefined ? undefined : Number(params.technician_active),
      },
    })

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
