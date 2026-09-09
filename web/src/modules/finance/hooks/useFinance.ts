import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { isApiError } from '@/shared/api/errors'
import {
  financeService,
  type AsaasConfigPayload,
  type ChargeReceivablePayload,
  type FinanceContractListParams,
  type FinanceContractPayload,
  type FinancePlanListParams,
  type FinancePlanPayload,
  type FinanceReceivableListParams,
  type FinanceReportParams,
  type FinanceSubscriptionListParams,
  type GenerateReceivablePayload,
} from '../services/finance.service'
import type { FinanceContractStatus } from '@/shared/types/models'

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

export function useFinanceContractsQuery(params: FinanceContractListParams) {
  return useQuery({
    queryKey: queryKeys.finance.contracts.list(params),
    queryFn: () => financeService.listContracts(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinanceContractQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.contracts.detail(id ?? ''),
    queryFn: () => financeService.getContract(id!),
    enabled: !!id,
  })
}

export function useCreateFinanceContract() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: FinanceContractPayload) => financeService.createContract(payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Contrato criado')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useUpdateFinanceContract(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<FinanceContractPayload>) =>
      financeService.updateContract(id, payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Contrato atualizado')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useDeleteFinanceContract() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => financeService.deleteContract(id),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Contrato removido')
    },
  })
}

export function useChangeFinanceContractStatus() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: FinanceContractStatus | string }) =>
      financeService.changeContractStatus(id, status),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Status do contrato atualizado')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
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
      toast.success('Assinatura reativada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useFinanceReceivablesQuery(params: FinanceReceivableListParams) {
  return useQuery({
    queryKey: queryKeys.finance.receivables.list(params),
    queryFn: () => financeService.listReceivables(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinanceReceivableQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.receivables.detail(id ?? ''),
    queryFn: () => financeService.getReceivable(id!),
    enabled: !!id,
  })
}

export function useGenerateFinanceReceivable() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: GenerateReceivablePayload) => financeService.generateReceivable(payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança gerada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useChargeFinanceReceivable() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ChargeReceivablePayload }) =>
      financeService.chargeReceivable(id, payload),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança enviada ao gateway')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useCancelFinanceReceivable() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => financeService.cancelReceivable(id),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança cancelada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useMarkFinanceReceivableReceived() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, paid_amount_cents }: { id: string; paid_amount_cents?: number | null }) =>
      financeService.markReceivableReceived(id, paid_amount_cents),
    onSuccess: () => {
      invalidateFinance(queryClient)
      toast.success('Cobrança marcada como recebida')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useAsaasConfigQuery() {
  return useQuery({
    queryKey: queryKeys.finance.asaasConfig(),
    queryFn: () => financeService.getAsaasConfig(),
  })
}

export function useUpdateAsaasConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: AsaasConfigPayload) => financeService.updateAsaasConfig(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.finance.asaasConfig() })
      toast.success('Configuração Asaas salva')
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

export function useFinancePortalReceivablesQuery(params: FinanceReceivableListParams) {
  return useQuery({
    queryKey: queryKeys.finance.portal.receivables(params),
    queryFn: () => financeService.portalReceivables(params),
    placeholderData: keepPreviousData,
  })
}

export function useFinancePortalReceivableQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.finance.portal.receivable(id ?? ''),
    queryFn: () => financeService.portalReceivable(id!),
    enabled: !!id,
  })
}

export function useFinancePortalPay() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ChargeReceivablePayload }) =>
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
