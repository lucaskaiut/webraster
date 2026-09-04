import { useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { checkoutService } from '../services/checkout.service'

export function usePublicPlansQuery() {
  return useQuery({
    queryKey: queryKeys.billing.plans.catalog(),
    queryFn: checkoutService.listPublicPlans,
  })
}

export function usePaymentMethodsQuery() {
  return useQuery({
    queryKey: queryKeys.billing.gateways.list(),
    queryFn: checkoutService.listPaymentMethods,
  })
}
