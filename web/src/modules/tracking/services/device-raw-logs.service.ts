import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'

export interface EquipmentRawLogLine {
  id: number
  line: string
  imei: string
  device_time: string | null
  received_at: string
}

export interface EquipmentRawLogResponse {
  lines: EquipmentRawLogLine[]
  last_id: number
}

export const deviceRawLogsService = {
  async tail(equipmentId: string, afterId = 0, limit = 300): Promise<EquipmentRawLogResponse> {
    const response = await http.get<ApiResponse<EquipmentRawLogResponse>>(
      `/equipments/${equipmentId}/raw-logs`,
      {
        params: {
          after_id: afterId > 0 ? afterId : undefined,
          limit,
        },
      },
    )

    return response.data.data
  },
}
