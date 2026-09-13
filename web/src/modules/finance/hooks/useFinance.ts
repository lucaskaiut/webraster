import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { isApiError } from '@/shared/api/errors'
import {
  financeService,
  type AssignFinanceSubscriptionPayload,
  type ChargeBillingPayload,
  type FinanceBillingListParams,
  type FinancePlanListParams,
  type FinancePlanPayload,
  type FinanceReportParams,
  type FinanceSubscriptionListParams,
  type GenerateBillingPayload,
  type PaymentGatewayConfigPayload,
  type UpdateFinanceSubscriptionPayload,
} from '../services/finance.service'

function invalidateFinance(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: queryKeys.finance.all })
}

export function useFinancePlansQuery(params: FinancePlanListParams) {
  return useQuery({
    queryKey: queryKeys.finance.plans.list(params),
    queryFn: () => financeService.listPlans(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinancePlanQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.plans.detail(id ?? ''),
    queryFn: () => financeService.getPlan(id!),
    enabled: !!id,
  })
}

export function useCreateFinancePlan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: FinancePlanPayload) => financeService.createPlan(payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Plano criado')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useUpdateFinancePlan(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<FinancePlanPayload>) => financeService.updatePlan(id, payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Plano atualizado')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useDeleteFinancePlan() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => financeService.deletePlan(id),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Plano removido')
    },
  })
}

export function useFinanceSubscriptionsQuery(params: FinanceSubscriptionListParams) {
  return useQuery({
    queryKey: queryKeys.finance.subscriptions.list(params),
    queryFn: () => financeService.listSubscriptions(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinanceSubscriptionQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.subscriptions.detail(id ?? ''),
    queryFn: () => financeService.getSubscription(id!),
    enabled: !!id,
  })
}

export function useAssignFinanceSubscription() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: AssignFinanceSubscriptionPayload) =>
      financeService.assignSubscription(payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Plano atribuído')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useCancelFinanceSubscription() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => financeService.cancelSubscription(id),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Assinatura cancelada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useReactivateFinanceSubscription() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => financeService.reactivateSubscription(id),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Assinatura reativada e dispositivos liberados')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useUpdateFinanceSubscription() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: UpdateFinanceSubscriptionPayload
    }) => financeService.updateSubscription(id, payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Assinatura atualizada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useFinanceClientOverviewQuery(clientId: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.clientOverview(clientId ?? ''),
    queryFn: () => financeService.clientOverview(clientId!),
    enabled: !!clientId,
  })
}

export function useFinanceBillingsQuery(params: FinanceBillingListParams) {
  return useQuery({
    queryKey: queryKeys.finance.billings.list(params),
    queryFn: () => financeService.listBillings(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinanceBillingQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.billings.detail(id ?? ''),
    queryFn: () => financeService.getBilling(id!),
    enabled: !!id,
  })
}

export function useGenerateFinanceBilling() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: GenerateBillingPayload) => financeService.generateBilling(payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança gerada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useChargeFinanceBilling() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ChargeBillingPayload }) =>
      financeService.chargeBilling(id, payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança enviada ao gateway')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useCancelFinanceBilling() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => financeService.cancelBilling(id),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança cancelada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useMarkFinanceBillingPaid() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, paid_amount_cents }: { id: string; paid_amount_cents?: number | null }) =>
      financeService.markBillingPaid(id, paid_amount_cents),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança marcada como paga')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function usePaymentGatewayConfigQuery(gateway?: string) {
  return useQuery({
    queryKey: [...queryKeys.finance.gatewayConfig(), gateway ?? 'default'],
    queryFn: () => financeService.getPaymentGatewayConfig(gateway),
  })
}

export function useUpdatePaymentGatewayConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: PaymentGatewayConfigPayload) =>
      financeService.updatePaymentGatewayConfig(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.finance.gatewayConfig() })
      toast.success('Configuração do gateway salva')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useFinanceDashboardQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.finance.dashboard(),
    queryFn: () => financeService.dashboard(),
    enabled,
  })
}

export function useFinanceReportsQuery(params: FinanceReportParams, enabled = true) {
  return useQuery({
    queryKey: queryKeys.finance.reports(params),
    queryFn: () => financeService.reports(params),
    enabled: enabled && !!params.type,
  })
}

export function useFinancePortalSubscriptionQuery() {
  return useQuery({
    queryKey: queryKeys.finance.portal.subscription(),
    queryFn: () => financeService.portalSubscription(),
  })
}

export function useFinancePortalBillingsQuery(params: FinanceBillingListParams) {
  return useQuery({
    queryKey: queryKeys.finance.portal.billings(params),
    queryFn: () => financeService.portalBillings(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinancePortalBillingQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.portal.billing(id ?? ''),
    queryFn: () => financeService.portalBilling(id!),
    enabled: !!id,
  })
}

export function useFinancePortalPay() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ChargeBillingPayload }) =>
      financeService.portalPay(id, payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Pagamento iniciado')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}
