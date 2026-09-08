import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export interface DeviceCommandType {
  type: string
  [key: string]: unknown
}

export interface SendDeviceCommandPayload {
  type: string
  attributes?: Record<string, unknown>
}

export interface DeviceCommandLog {
  id: string
  command_type: string
  payload: Record<string, unknown> | null
  status: 'pending' | 'success' | 'failed'
  requested_at: string | null
  executed_at: string | null
  response: Record<string, unknown> | null
}

export const deviceCommandsService = {
  async list(deviceId: string): Promise<DeviceCommandType[]> {
    const response = await http.get<ApiResponse<DeviceCommandType[]>>(`/devices/${deviceId}/commands`)
    return response.data.data
  },

  async send(deviceId: string, payload: SendDeviceCommandPayload): Promise<DeviceCommandLog> {
    const response = await http.post<ApiResponse<DeviceCommandLog>>(
      `/devices/${deviceId}/commands`,
      payload,
    )
    return response.data.data
  },
}
