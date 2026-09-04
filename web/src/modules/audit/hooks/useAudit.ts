import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { auditService } from '../services/audit.service'

export function useAuditQuery(params: { page?: number; per_page?: number; action?: string }) {
  return useQuery({
    queryKey: queryKeys.audit.list(params),
    queryFn: () => auditService.list(params),
    placeholderData: keepPreviousData,
  })
}
