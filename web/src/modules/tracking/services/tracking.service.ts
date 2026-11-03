import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type {
  EquipmentLiveItem,
  GpsPosition,
  PublicTrackingShare,
  TrackingGatewayStatus,
  TrackingLiveVehicle,
  TrackingShareLink,
} from '@/shared/types/models'

export const trackingService = {
  async status(): Promise<TrackingGatewayStatus> {
    const response = await http.get<ApiResponse<TrackingGatewayStatus>>('/tracking/status')

    return response.data.data
  },

  async live(search?: string): Promise<TrackingLiveVehicle[]> {
    const response = await http.get<ApiResponse<TrackingLiveVehicle[]>>('/tracking/live', {
      params: search ? { search } : undefined,
    })

    return response.data.data
  },

  /** Posições de equipamentos ainda sem veículo (estoque). */
  async liveEquipments(search?: string): Promise<EquipmentLiveItem[]> {
    const response = await http.get<ApiResponse<EquipmentLiveItem[]>>('/tracking/equipments/live', {
      params: search ? { search } : undefined,
    })

    return response.data.data
  },

  async history(vehicleId: string, from: string, to: string): Promise<GpsPosition[]> {
    const response = await http.get<ApiResponse<GpsPosition[]>>(
      `/tracking/vehicles/${vehicleId}/history`,
      { params: { from, to } },
    )

    return response.data.data
  },

  async createShareLink(payload: { vehicle_ids: string[]; expires_in_hours: number }): Promise<TrackingShareLink> {
    const response = await http.post<ApiResponse<TrackingShareLink>>('/tracking/share-links', payload)

    return response.data.data
  },

  /** Rota pública (sem login) do link de compartilhamento. */
  async publicShare(token: string): Promise<PublicTrackingShare> {
    const response = await http.get<ApiResponse<PublicTrackingShare>>(
      `/public/tracking/${encodeURIComponent(token)}`,
    )

    return response.data.data
  },
}
