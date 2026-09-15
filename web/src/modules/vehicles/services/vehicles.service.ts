import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  EquipmentAssignmentEvent,
  Vehicle,
  VehicleTransmission,
} from '@/shared/types/models'

export interface VehicleListParams extends ListParams {
  client_id?: string
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
}
