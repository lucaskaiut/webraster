import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import {
  reportsService,
  type CommandReportFilters,
  type PositionReportFilters,
  type StopReportFilters,
  type EventReportFilters,
  type TripReportFilters,
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

export function useStopsReportQuery(filters: StopReportFilters, enabled = true) {
  return useQuery({
    queryKey: queryKeys.reports.stops(filters),
    queryFn: () => reportsService.stops(filters),
    enabled,
    placeholderData: keepPreviousData,
  })
}

export function useTripsReportQuery(filters: TripReportFilters | null, enabled = true) {
  return useQuery({
    queryKey: queryKeys.reports.trips(filters ?? { vehicle_id: '' }),
    queryFn: () => reportsService.trips(filters!),
    enabled: enabled && filters !== null && Boolean(filters.vehicle_id),
    placeholderData: keepPreviousData,
  })
}

export function useEventsReportQuery(filters: EventReportFilters, enabled = true) {
  return useQuery({
    queryKey: queryKeys.reports.events(filters),
    queryFn: () => reportsService.events(filters),
    enabled,
    placeholderData: keepPreviousData,
  })
}
