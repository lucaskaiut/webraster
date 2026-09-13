import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { VehicleData, VehicleDataConfig } from '@/shared/types/models'

export interface VehicleDataConfigPayload {
  provider: string
  is_active?: boolean
  credentials: Record<string, string>
}

export const vehicleDataService = {
  async lookup(plate: string): Promise<VehicleData> {
    const response = await http.get<ApiResponse<VehicleData>>('/vehicle-data/lookup', {
      params: { plate },
    })
    return response.data.data
  },

  async getConfig(provider?: string): Promise<VehicleDataConfig> {
    const response = await http.get<ApiResponse<VehicleDataConfig>>('/vehicle-data/config', {
      params: provider ? { provider } : undefined,
    })
    return response.data.data
  },

  async updateConfig(payload: VehicleDataConfigPayload): Promise<VehicleDataConfig> {
    const response = await http.put<ApiResponse<VehicleDataConfig>>('/vehicle-data/config', payload)
    return response.data.data
  },
}
