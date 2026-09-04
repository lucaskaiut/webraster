import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Role } from '@/shared/types/models'
import type { Permission } from '@/shared/constants/permissions'

export interface RolePayload {
  name: string
  description?: string | null
  permissions: Permission[]
}

export const rolesService = {
  async list(params: ListParams): Promise<PaginatedResponse<Role>> {
    const response = await http.get<PaginatedResponse<Role>>('/roles', { params })

    return response.data
  },

  async get(id: number): Promise<Role> {
    const response = await http.get<ApiResponse<Role>>(`/roles/${id}`)

    return response.data.data
  },

  async create(payload: RolePayload): Promise<Role> {
    const response = await http.post<ApiResponse<Role>>('/roles', payload)

    return response.data.data
  },

  async update(id: number, payload: RolePayload): Promise<Role> {
    const response = await http.put<ApiResponse<Role>>(`/roles/${id}`, payload)

    return response.data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/roles/${id}`)
  },
}
