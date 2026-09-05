import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Equipment, EquipmentAssignmentEvent } from '@/shared/types/models'

export interface EquipmentListParams extends ListParams {
  available?: boolean
  vehicle_id?: string
}

export interface EquipmentPayload {
  imei: string
  model?: string | null
  iccid?: string | null
  carrier?: string | null
  is_active?: boolean
}

export const equipmentsService = {
  async list(params: EquipmentListParams): Promise<PaginatedResponse<Equipment>> {
    const response = await http.get<PaginatedResponse<Equipment>>('/equipments', { params })

    return response.data
  },

  async get(id: string): Promise<Equipment> {
    const response = await http.get<ApiResponse<Equipment>>(`/equipments/${id}`)

    return response.data.data
  },

  async create(payload: EquipmentPayload): Promise<Equipment> {
    const response = await http.post<ApiResponse<Equipment>>('/equipments', payload)

    return response.data.data
  },

  async update(id: string, payload: EquipmentPayload): Promise<Equipment> {
    const response = await http.put<ApiResponse<Equipment>>(`/equipments/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/equipments/${id}`)
  },

  async history(id: string): Promise<EquipmentAssignmentEvent[]> {
    const response = await http.get<ApiResponse<EquipmentAssignmentEvent[]>>(
      `/equipments/${id}/assignment-history`,
    )

    return response.data.data
  },
}
