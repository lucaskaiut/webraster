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

export function useTrackingLiveQuery() {
  return useQuery({
    queryKey: queryKeys.tracking.live(),
    queryFn: () => trackingService.live(),
    refetchInterval: 15_000,
    placeholderData: keepPreviousData,
  })
}
