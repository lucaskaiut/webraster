import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import {
  reportsService,
  type CommandReportFilters,
  type PositionReportFilters,
} from '../services/reports.service'

export function useCommandsReportQuery(filters: CommandReportFilters, enabled = true) {
  return useQuery({
    queryKey: queryKeys.reports.commands(filters),
    queryFn: () => reportsService.commands(filters),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function usePositionsReportQuery(filters: PositionReportFilters | null, enabled = true) {
  return useQuery({
    queryKey: queryKeys.reports.positions(filters ?? { vehicle_id: '' }),
    queryFn: () => reportsService.positions(filters!),
    enabled: enabled && filters !== null && Boolean(filters.vehicle_id),
    placeholderData: keepPreviousData,
  })
}
