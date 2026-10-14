import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { MonitoringDashboardMetrics } from '@/shared/types/models'

export const dashboardService = {
  async monitoring(): Promise<MonitoringDashboardMetrics> {
    const response = await http.get<ApiResponse<MonitoringDashboardMetrics>>('/dashboard/monitoring')

    return response.data.data
  },
}
