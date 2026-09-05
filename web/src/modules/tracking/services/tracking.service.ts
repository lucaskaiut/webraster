import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { GpsPosition, TrackingGatewayStatus, TrackingLiveVehicle } from '@/shared/types/models'

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

  async history(vehicleId: string, from: string, to: string): Promise<GpsPosition[]> {
    const response = await http.get<ApiResponse<GpsPosition[]>>(
      `/tracking/vehicles/${vehicleId}/history`,
      { params: { from, to } },
    )

    return response.data.data
  },
}
