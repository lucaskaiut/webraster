import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  ServiceOrder,
  ServiceOrderHistory,
  ServiceOrderKanban,
  ServiceOrderPriority,
  ServiceOrderStatus,
  ServiceOrderType,
} from '@/shared/types/models'

export interface ServiceOrderListParams extends ListParams {
  status?: ServiceOrderStatus | string
  type?: ServiceOrderType | string
  priority?: ServiceOrderPriority | string
  client_id?: string
  vehicle_id?: string
  technician_id?: string
  from?: string
  to?: string
}

export interface ServiceOrderPayload {
  type: ServiceOrderType | string
  priority?: ServiceOrderPriority | string
  client_id: string
  vehicle_id?: string | null
  equipment_id?: string | null
  technician_id?: string | null
  scheduled_start_at?: string | null
  scheduled_end_at?: string | null
  description?: string | null
  notes?: string | null
  execution_notes?: string | null
  ignore_schedule_conflict?: boolean
}

export interface ChangeStatusPayload {
  status: ServiceOrderStatus | string
  cancellation_reason?: string | null
  execution_notes?: string | null
}

export const serviceOrdersService = {
  async list(params: ServiceOrderListParams): Promise<PaginatedResponse<ServiceOrder>> {
    const response = await http.get<PaginatedResponse<ServiceOrder>>('/service-orders', { params })
    return response.data
  },

  async kanban(params?: ServiceOrderListParams): Promise<ServiceOrderKanban> {
    const response = await http.get<ApiResponse<ServiceOrderKanban>>('/service-orders/kanban', {
      params,
    })
    return response.data.data
  },

  async calendar(params?: ServiceOrderListParams): Promise<ServiceOrder[]> {
    const response = await http.get<ApiResponse<ServiceOrder[]>>('/service-orders/calendar', {
      params,
    })
    return response.data.data
  },

  async get(id: string): Promise<ServiceOrder> {
    const response = await http.get<ApiResponse<ServiceOrder>>(`/service-orders/${id}`)
    return response.data.data
  },

  async create(payload: ServiceOrderPayload): Promise<ServiceOrder> {
    const response = await http.post<ApiResponse<ServiceOrder>>('/service-orders', payload)
    return response.data.data
  },

  async update(id: string, payload: Partial<ServiceOrderPayload>): Promise<ServiceOrder> {
    const response = await http.put<ApiResponse<ServiceOrder>>(`/service-orders/${id}`, payload)
    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/service-orders/${id}`)
  },

  async changeStatus(id: string, payload: ChangeStatusPayload): Promise<ServiceOrder> {
    const response = await http.patch<ApiResponse<ServiceOrder>>(
      `/service-orders/${id}/status`,
      payload,
    )
    return response.data.data
  },

  async history(id: string): Promise<ServiceOrderHistory[]> {
    const response = await http.get<ApiResponse<ServiceOrderHistory[]>>(
      `/service-orders/${id}/history`,
    )
    return response.data.data
  },
}
