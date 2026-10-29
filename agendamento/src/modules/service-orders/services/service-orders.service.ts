import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  FinanceBilling,
  ServiceOrder,
  ServiceOrderAttachment,
  ServiceOrderHistory,
  ServiceOrderKanban,
  ServiceOrderPhoto,
  ServiceOrderPriority,
  ServiceOrderStatus,
} from '@/shared/types/models'

export interface ServiceOrderListParams extends ListParams {
  status?: ServiceOrderStatus | string
  priority?: ServiceOrderPriority | string
  client_id?: string
  vehicle_id?: string
  technician_id?: string
  from?: string
  to?: string
}

export interface ServiceOrderItemPayload {
  service_id: string
  quantity: number
  unit_amount_cents: number
}

export interface ServiceOrderPayload {
  priority?: ServiceOrderPriority | string
  status?: ServiceOrderStatus | string
  client_id?: string | null
  vehicle_id?: string | null
  equipment_id?: string | null
  technician_id?: string | null
  scheduled_start_at?: string | null
  scheduled_end_at?: string | null
  address?: string | null
  estimated_duration_minutes?: number | null
  description?: string | null
  notes?: string | null
  installation_location?: string | null
  installation_location_photo_path?: string | null
  execution_notes?: string | null
  cancellation_reason?: string | null
  items?: ServiceOrderItemPayload[]
  ignore_schedule_conflict?: boolean
}

export interface ScheduleEstimatePayload {
  date: string
  /** Horário específico para o qual a prévia/mapa deve ser calculada. */
  scheduled_start_at?: string | null
  client_id?: string | null
  address?: string | null
  technician_id?: string | null
  service_order_id?: string | null
  duration_minutes?: number | null
  items?: Array<{ service_id: string; quantity: number }>
}

export interface ScheduleTravel {
  minutes: number
  distance_meters: number
  source: 'google' | 'osrm' | 'estimate'
}

export interface ScheduleRoundTrip {
  minutes: number
  distance_meters: number
  source: 'google' | 'osrm' | 'estimate'
}

export interface ScheduleRoutePoint {
  latitude: number
  longitude: number
  address: string | null
  kind: 'base' | 'order' | 'destination'
  order_code: string | null
}

/** Pontos no formato [latitude, longitude]. */
export type ScheduleRouteGeometry = Array<[number, number]>

export interface ScheduleRoute {
  origin: ScheduleRoutePoint | null
  destination: ScheduleRoutePoint | null
  return_to: ScheduleRoutePoint | null
  outbound: ScheduleRouteGeometry
  return: ScheduleRouteGeometry
}

export interface ScheduleEstimate {
  address: string | null
  latitude: number | null
  longitude: number | null
  duration_minutes: number
  duration_source: 'manual' | 'services' | 'default'
  travel: ScheduleTravel | null
  travel_return: ScheduleTravel | null
  travel_round_trip: ScheduleRoundTrip | null
  travel_origin: string | null
  travel_origin_kind: 'base' | 'order'
  travel_origin_order_code: string | null
  travel_return_address: string | null
  travel_return_kind: 'base' | 'order'
  travel_return_order_code: string | null
  route: ScheduleRoute | null
  date: string
  timezone: string
  slots: Array<{
    start: string
    end: string
    available: boolean
    reason: string | null
    blocked_by: {
      id: string
      code: string
      client: string | null
      service_start: string | null
      service_end: string | null
      blocked_start: string
      blocked_end: string
    } | null
  }>
  warnings: string[]
}

export interface AddressSuggestion {
  title: string
  subtitle: string | null
  address: string
  latitude: number | null
  longitude: number | null
}

export interface ChangeStatusPayload {
  status: ServiceOrderStatus | string
  cancellation_reason?: string | null
  execution_notes?: string | null
}

function appendFormValue(data: FormData, key: string, value: unknown): void {
  if (value === undefined) return

  if (Array.isArray(value)) {
    value.forEach((item, index) => appendFormValue(data, `${key}[${index}]`, item))
    return
  }

  if (value !== null && typeof value === 'object') {
    Object.entries(value).forEach(([field, item]) => appendFormValue(data, `${key}[${field}]`, item))
    return
  }

  if (typeof value === 'boolean') {
    data.append(key, value ? '1' : '0')
    return
  }

  // Strings vazias viram null na API (ConvertEmptyStringsToNull).
  data.append(key, value === null ? '' : String(value))
}

function toFormData(payload: ServiceOrderPayload, attachments: File[]): FormData {
  const data = new FormData()

  Object.entries(payload).forEach(([key, value]) => appendFormValue(data, key, value))
  attachments.forEach((file) => data.append('attachments[]', file))

  return data
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

  /** Com documentos, a OS e os anexos seguem juntos em multipart. */
  async create(payload: ServiceOrderPayload, attachments: File[] = []): Promise<ServiceOrder> {
    const body = attachments.length > 0 ? toFormData(payload, attachments) : payload
    const response = await http.post<ApiResponse<ServiceOrder>>('/service-orders', body)
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

  async generateBilling(id: string, payload?: { due_at?: string | null }): Promise<FinanceBilling> {
    const response = await http.post<ApiResponse<FinanceBilling>>(
      `/service-orders/${id}/billing`,
      payload ?? {},
    )
    return response.data.data
  },

  async addPhoto(id: string, path: string): Promise<ServiceOrderPhoto> {
    const response = await http.post<ApiResponse<ServiceOrderPhoto>>(`/service-orders/${id}/photos`, {
      path,
    })
    return response.data.data
  },

  async removePhoto(id: string, photoId: string): Promise<void> {
    await http.delete(`/service-orders/${id}/photos/${photoId}`)
  },

  async estimate(payload: ScheduleEstimatePayload): Promise<ScheduleEstimate> {
    const response = await http.post<ApiResponse<ScheduleEstimate>>('/scheduling/estimate', payload)
    return response.data.data
  },

  async searchAddress(query: string, limit = 6): Promise<AddressSuggestion[]> {
    const response = await http.get<ApiResponse<AddressSuggestion[]>>(
      '/scheduling/address-search',
      { params: { q: query, limit } },
    )
    return response.data.data
  },

  async pdf(id: string): Promise<Blob> {
    const response = await http.get<Blob>(`/service-orders/${id}/pdf`, { responseType: 'blob' })
    return response.data
  },

  async loanAcceptanceSignature(id: string): Promise<Blob> {
    const response = await http.get<Blob>(`/service-orders/${id}/loan-acceptance/signature`, {
      responseType: 'blob',
    })
    return response.data
  },

  async uploadAttachment(id: string, file: File): Promise<ServiceOrderAttachment> {
    const body = new FormData()
    body.append('file', file)
    const response = await http.post<ApiResponse<ServiceOrderAttachment>>(
      `/service-orders/${id}/attachments`,
      body,
    )
    return response.data.data
  },

  async downloadAttachment(id: string, attachmentId: string): Promise<Blob> {
    const response = await http.get<Blob>(`/service-orders/${id}/attachments/${attachmentId}`, {
      responseType: 'blob',
    })
    return response.data
  },

  async removeAttachment(id: string, attachmentId: string): Promise<void> {
    await http.delete(`/service-orders/${id}/attachments/${attachmentId}`)
  },

  async checklistPhoto(
    orderId: string,
    checklistId: string,
    questionId: string,
    photoId: string,
  ): Promise<Blob> {
    const response = await http.get<Blob>(
      `/service-orders/${orderId}/checklists/${checklistId}/questions/${questionId}/photos/${photoId}`,
      { responseType: 'blob' },
    )
    return response.data
  },
}
