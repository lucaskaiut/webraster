import { useQuery } from '@tanstack/react-query'
import { dashboardService } from '../services/dashboard.service'

export const MONITORING_DASHBOARD_POLL_MS = 30_000

export function useMonitoringDashboardQuery(enabled = true) {
  return useQuery({
    queryKey: ['dashboard', 'monitoring'],
    queryFn: () => dashboardService.monitoring(),
    enabled,
    refetchInterval: MONITORING_DASHBOARD_POLL_MS,
  })
}
