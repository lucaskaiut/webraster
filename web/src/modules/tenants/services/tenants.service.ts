import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  Tenant,
  TenantModule,
  TenantModuleKey,
  TenantVehicleAlertDefault,
  User,
} from '@/shared/types/models'

export interface CreateChildTenantPayload {
  tenant: {
    name: string
    document: string
    email: string
    phone: string
    is_active: boolean
    modules: TenantModuleKey[]
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
    is_active: boolean
    modules: TenantModuleKey[]
  }
}

export interface UpdateTenantPayload {
  name?: string
  identifier?: string
  document?: string
  email?: string
  phone?: string | null
  logo_path?: string | null
  favicon_path?: string | null
  signature_path?: string | null
  late_fine_percent?: number | null
  late_interest_percent?: number | null
  app_name?: string | null
  app_icon_path?: string | null
  app_logo_path?: string | null
  app_primary_color?: string | null
  app_secondary_color?: string | null
  require_equipment_loan_acceptance?: boolean
  equipment_loan_term?: string | null
  vehicle_alert_defaults?: TenantVehicleAlertDefault[]
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

  async listModules(): Promise<TenantModule[]> {
    const response = await http.get<ApiResponse<TenantModule[]>>('/tenant/modules')

    return response.data.data
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
