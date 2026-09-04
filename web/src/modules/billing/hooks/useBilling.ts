import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { billingService, type PlanPayload } from '../services/billing.service'

export function usePlansQuery() {
  return useQuery({
    queryKey: queryKeys.billing.plans.list(),
    queryFn: async () => {
      const result = await billingService.listPlans()

      return result.data
    },
  })
}

export function usePlanQuery(id: string) {
  return useQuery({
    queryKey: queryKeys.billing.plans.detail(id),
    queryFn: () => billingService.getPlan(id),
    enabled: Boolean(id),
  })
}

export function usePlanCatalogQuery() {
  return useQuery({
    queryKey: queryKeys.billing.plans.catalog(),
    queryFn: billingService.catalog,
  })
}

export function useCreatePlan() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PlanPayload) => billingService.createPlan(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.billing.plans.all })
      toast.success('Plano criado', 'O plano já está disponível para assinatura.')
    },
  })
}

export function useUpdatePlan() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: PlanPayload & { id: string }) =>
      billingService.updatePlan(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.billing.plans.all })
      toast.success('Plano atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeactivatePlan() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => billingService.deactivatePlan(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.billing.plans.all })
      toast.success('Plano inativado', 'Ele não aparecerá mais no catálogo.')
    },
  })
}

export function useSubscriptionQuery() {
  return useQuery({
    queryKey: queryKeys.billing.subscription.current(),
    queryFn: billingService.getSubscription,
  })
}

export function useGatewaysQuery() {
  return useQuery({
    queryKey: queryKeys.billing.gateways.list(),
    queryFn: billingService.listGateways,
    staleTime: 5 * 60 * 1000,
  })
}

export function useCreateSubscription() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({
      planId,
      paymentGateway,
    }: {
      planId: string
      paymentGateway?: string
    }) => billingService.createSubscription(planId, paymentGateway),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.billing.all })
      toast.success('Assinatura criada', 'Se houver cobrança, pague em Cobranças.')
    },
  })
}

export function useCancelSubscription() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => billingService.cancelSubscription(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.billing.all })
      toast.success('Assinatura cancelada', 'O acesso será bloqueado conforme a política.')
    },
  })
}

export function useReactivateSubscription() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => billingService.reactivateSubscription(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.billing.all })
      toast.success('Assinatura reativada', 'O acesso foi restaurado.')
    },
  })
}

export function useInvoicesQuery() {
  return useQuery({
    queryKey: queryKeys.billing.invoices.list(),
    queryFn: async () => {
      const result = await billingService.listInvoices()

      return result.data
    },
  })
}
