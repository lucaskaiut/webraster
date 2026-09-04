import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Tenant, User } from '@/shared/types/models'

export interface CreateChildTenantPayload {
  tenant: {
    name: string
    document: string
    email: string
    phone: string
    domain: string
  }
  user: {
    name: string
    email: string
    password: string
  }
  plan_id: string | null
}

export const tenantsService = {
  async listChildren(params: ListParams): Promise<PaginatedResponse<Tenant>> {
    const response = await http.get<PaginatedResponse<Tenant>>('/tenant/children', { params })

    return response.data
  },

  async createChild(payload: CreateChildTenantPayload): Promise<{ tenant: Tenant; user: User }> {
    const response = await http.post<ApiResponse<{ tenant: Tenant; user: User }>>(
      '/tenant/children',
      payload,
    )

    return response.data.data
  },
}
