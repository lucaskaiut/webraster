import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { externalApisService } from '../services/external-apis.service'
import type { ExternalApiMetricsParams, ExternalApiRequestsParams } from '../types'

export function useExternalApiServicesQuery() {
  return useQuery({
    queryKey: queryKeys.externalApis.services(),
    queryFn: () => externalApisService.services(),
    staleTime: Infinity,
  })
}

export function useExternalApiMetricsQuery(params: ExternalApiMetricsParams) {
  return useQuery({
    queryKey: queryKeys.externalApis.metrics(params),
    queryFn: () => externalApisService.metrics(params),
    placeholderData: keepPreviousData,
  })
}

export function useExternalApiRequestsQuery(params: ExternalApiRequestsParams) {
  return useQuery({
    queryKey: queryKeys.externalApis.requests(params),
    queryFn: () => externalApisService.requests(params),
    placeholderData: keepPreviousData,
  })
}

export function useExternalApiRequestQuery(id: number | null) {
  return useQuery({
    queryKey: queryKeys.externalApis.request(id ?? 0),
    queryFn: () => externalApisService.request(id as number),
    enabled: id !== null,
  })
}
