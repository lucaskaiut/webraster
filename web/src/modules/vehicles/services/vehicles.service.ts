import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  EquipmentAssignmentEvent,
  Vehicle,
  VehicleImage,
  VehicleTransmission,
} from '@/shared/types/models'

import type { AlertTypeValue } from '@/modules/alerts/lib/alert-types'

export interface VehicleListParams extends ListParams {
  client_id?: string
}

export interface VehicleAlertConfigPayload {
  type: AlertTypeValue
  alarm_code?: string | null
  is_enabled: boolean
  notify_in_app: boolean
  notify_monitoring: boolean
  notify_push: boolean
  notify_email: boolean
}

export interface VehiclePayload {
  client_id: string
  plate: string
  chassis?: string | null
  renavam?: string | null
  brand?: string | null
  model?: string | null
  color?: string | null
  year?: number | null
  vehicle_type?: number | null
  transmission?: VehicleTransmission | null
  odometer?: number | null
  max_speed_kmh?: number | null
  speed_hysteresis_percent?: number | null
  speed_min_duration_seconds?: number | null
  average_consumption?: number | null
  tank_capacity?: number | null
  crlv_file?: string | null
  fipe_code?: string | null
  fipe_model_year?: string | null
  fipe_fuel?: string | null
  fipe_reference_month?: string | null
  fipe_value?: string | null
  fipe_model?: string | null
  fipe_brand?: string | null
  fipe_score?: number | null
  is_active?: boolean
  alert_configs?: VehicleAlertConfigPayload[]
}

export interface AssignmentPayload {
  equipment_id?: string
  notes?: string | null
  occurred_at?: string | null
}

export interface AssignmentResult {
  vehicle: Vehicle
  event: EquipmentAssignmentEvent
}

export const vehiclesService = {
  async list(params: VehicleListParams): Promise<PaginatedResponse<Vehicle>> {
    const response = await http.get<PaginatedResponse<Vehicle>>('/vehicles', { params })

    return response.data
  },

  async get(id: string): Promise<Vehicle> {
    const response = await http.get<ApiResponse<Vehicle>>(`/vehicles/${id}`)

    return response.data.data
  },

  async create(payload: VehiclePayload): Promise<Vehicle> {
    const response = await http.post<ApiResponse<Vehicle>>('/vehicles', payload)

    return response.data.data
  },

  async update(id: string, payload: VehiclePayload): Promise<Vehicle> {
    const response = await http.put<ApiResponse<Vehicle>>(`/vehicles/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/vehicles/${id}`)
  },

  async history(id: string): Promise<EquipmentAssignmentEvent[]> {
    const response = await http.get<ApiResponse<EquipmentAssignmentEvent[]>>(
      `/vehicles/${id}/equipment-history`,
    )

    return response.data.data
  },

  async install(id: string, payload: AssignmentPayload): Promise<AssignmentResult> {
    const response = await http.post<ApiResponse<AssignmentResult>>(
      `/vehicles/${id}/equipment/install`,
      payload,
    )

    return response.data.data
  },

  async removeEquipment(id: string, payload: AssignmentPayload = {}): Promise<AssignmentResult> {
    const response = await http.post<ApiResponse<AssignmentResult>>(
      `/vehicles/${id}/equipment/remove`,
      payload,
    )

    return response.data.data
  },

  async swap(id: string, payload: AssignmentPayload): Promise<AssignmentResult> {
    const response = await http.post<ApiResponse<AssignmentResult>>(
      `/vehicles/${id}/equipment/swap`,
      payload,
    )

    return response.data.data
  },

  async addImage(id: string, path: string): Promise<VehicleImage> {
    const response = await http.post<ApiResponse<VehicleImage>>(`/vehicles/${id}/images`, { path })

    return response.data.data
  },

  async removeImage(id: string, imageId: string): Promise<void> {
    await http.delete(`/vehicles/${id}/images/${imageId}`)
  },
}
