import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Alert, AlertConfig, AlertDashboardStats } from '@/shared/types/models'

export interface AlertListParams extends ListParams {
  vehicle_id?: string
  type?: string
  status?: string
  severity?: string
  from?: string
  to?: string
  sort?: 'asc' | 'desc'
}

export interface AlertConfigPayload {
  name?: string | null
  type: string
  client_id?: string | null
  vehicle_id?: string | null
  is_enabled?: boolean
  notify_in_app?: boolean
  notify_email?: boolean
  settings?: AlertConfig['settings']
}

export const alertsService = {
  async list(params: AlertListParams): Promise<PaginatedResponse<Alert>> {
    const response = await http.get<PaginatedResponse<Alert>>('/alerts', { params })
    return response.data
  },

  async map(status = 'open'): Promise<Alert[]> {
    const response = await http.get<ApiResponse<Alert[]>>('/alerts/map', { params: { status } })
    return response.data.data
  },

  async dashboard(): Promise<AlertDashboardStats> {
    const response = await http.get<ApiResponse<AlertDashboardStats>>('/alerts/dashboard')
    return response.data.data
  },

  async get(id: string): Promise<Alert> {
    const response = await http.get<ApiResponse<Alert>>(`/alerts/${id}`)
    return response.data.data
  },

  async acknowledge(id: string): Promise<Alert> {
    const response = await http.post<ApiResponse<Alert>>(`/alerts/${id}/acknowledge`)
    return response.data.data
  },

  async resolve(id: string): Promise<Alert> {
    const response = await http.post<ApiResponse<Alert>>(`/alerts/${id}/resolve`)
    return response.data.data
  },

  async configs(): Promise<AlertConfig[]> {
    const response = await http.get<ApiResponse<AlertConfig[]>>('/alert-configs')
    return response.data.data
  },

  async createConfig(payload: AlertConfigPayload): Promise<AlertConfig> {
    const response = await http.post<ApiResponse<AlertConfig>>('/alert-configs', payload)
    return response.data.data
  },

  async updateConfig(id: string, payload: Partial<AlertConfigPayload>): Promise<AlertConfig> {
    const response = await http.put<ApiResponse<AlertConfig>>(`/alert-configs/${id}`, payload)
    return response.data.data
  },

  async deleteConfig(id: string): Promise<void> {
    await http.delete(`/alert-configs/${id}`)
  },
}
