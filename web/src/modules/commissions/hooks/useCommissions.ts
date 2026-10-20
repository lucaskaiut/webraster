import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import {
  commissionsService,
  type CommissionListParams,
} from '../services/commissions.service'

export function useCommissionsQuery(params: CommissionListParams) {
  return useQuery({
    queryKey: queryKeys.commissions.list(params),
    queryFn: () => commissionsService.list(params),
    placeholderData: keepPreviousData,
  })
}
