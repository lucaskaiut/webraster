import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Tenant, User } from '@/shared/types/models'

export interface CreateChildTenantPayload {
  tenant: {
    name: string
    document: string
    email: string
    phone: string
  }
  user: {
    name: string
    email: string
    password: string
  }
}

export interface UpdateChildTenantPayload {
  tenant: {
    name: string
    document: string
    email: string
    phone: string
  }
}

export interface UpdateTenantPayload {
  name?: string
  document?: string
  email?: string
  phone?: string | null
  logo_path?: string | null
  favicon_path?: string | null
}

export const tenantsService = {
  async getCurrent(): Promise<Tenant> {
    const response = await http.get<ApiResponse<Tenant>>('/tenant')

    return response.data.data
  },

  async updateCurrent(payload: UpdateTenantPayload): Promise<Tenant> {
    const response = await http.put<ApiResponse<Tenant>>('/tenant', payload)

    return response.data.data
  },

  async listChildren(params: ListParams): Promise<PaginatedResponse<Tenant>> {
    const response = await http.get<PaginatedResponse<Tenant>>('/tenant/children', { params })

    return response.data
  },

  async getChild(id: string): Promise<Tenant> {
    const response = await http.get<ApiResponse<Tenant>>(`/tenant/children/${id}`)

    return response.data.data
  },

  async createChild(payload: CreateChildTenantPayload): Promise<{ tenant: Tenant; user: User }> {
    const response = await http.post<ApiResponse<{ tenant: Tenant; user: User }>>(
      '/tenant/children',
      payload,
    )

    return response.data.data
  },

  async updateChild(id: string, payload: UpdateChildTenantPayload): Promise<Tenant> {
    const response = await http.put<ApiResponse<Tenant>>(`/tenant/children/${id}`, payload)

    return response.data.data
  },
}
