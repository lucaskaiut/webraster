import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { trackingService } from '../services/tracking.service'

export function useTrackingStatusQuery() {
  return useQuery({
    queryKey: queryKeys.tracking.status(),
    queryFn: trackingService.status,
    staleTime: 60_000,
  })
}

export function useTrackingLiveQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.tracking.live(),
    queryFn: () => trackingService.live(),
    refetchInterval: 15_000,
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function useTrackingHistoryQuery(
  vehicleId: string | undefined,
  from: string,
  to: string,
  enabled = true,
) {
  return useQuery({
    queryKey: queryKeys.tracking.history(vehicleId ?? '', from, to),
    queryFn: () => trackingService.history(vehicleId!, from, to),
    enabled: enabled && Boolean(vehicleId) && Boolean(from) && Boolean(to),
    placeholderData: keepPreviousData,
  })
}
