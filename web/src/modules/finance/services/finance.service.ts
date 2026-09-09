import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  AsaasEnvironment,
  BillingPeriodicity,
  FinanceContract,
  FinanceContractStatus,
  FinanceDashboardMetrics,
  FinancePaymentMethod,
  FinancePlan,
  FinanceReceivable,
  FinanceReportResult,
  FinanceReportType,
  FinanceSubscription,
  TenantAsaasConfig,
} from '@/shared/types/models'

export interface FinancePlanListParams extends ListParams {
  is_active?: boolean | string
}

export interface FinancePlanPayload {
  name: string
  description?: string | null
  amount_cents: number
  periodicity?: BillingPeriodicity | string
  device_limit?: number | null
  is_active?: boolean
}

export interface FinanceContractListParams extends ListParams {
  status?: string
  client_id?: string
  plan_id?: string
}

export interface FinanceContractPayload {
  client_id: string
  plan_id?: string | null
  status?: FinanceContractStatus | string
  starts_at: string
  ends_at?: string | null
  periodicity?: BillingPeriodicity | string
  due_day?: number
  amount_cents?: number
  discount_cents?: number
  fine_percent?: number
  interest_percent?: number
  device_quantity?: number
  auto_renew?: boolean
  block_on_overdue?: boolean
  block_after_days?: number
  notes?: string | null
}

export interface FinanceSubscriptionListParams extends ListParams {
  status?: string
  client_id?: string
}

export interface FinanceReceivableListParams extends ListParams {
  status?: string
  client_id?: string
  contract_id?: string
  due_from?: string
  due_to?: string
}

export interface GenerateReceivablePayload {
  contract_id: string
  due_at?: string | null
}

export interface ChargeReceivablePayload {
  payment_method: FinancePaymentMethod | string
  credit_card?: {
    holderName: string
    number: string
    expiryMonth: string
    expiryYear: string
    ccv: string
  }
  creditCardHolderInfo?: Record<string, unknown>
}

export interface AsaasConfigPayload {
  environment?: AsaasEnvironment | string
  api_key?: string | null
  webhook_token?: string | null
  is_active?: boolean
}

export interface FinanceReportParams {
  type: FinanceReportType | string
  from?: string
  to?: string
  status?: string
}

export const financeService = {
  async listPlans(params: FinancePlanListParams): Promise<PaginatedResponse<FinancePlan>> {
    const response = await http.get<PaginatedResponse<FinancePlan>>('/finance/plans', { params })
    return response.data
  },

  async getPlan(id: string): Promise<FinancePlan> {
    const response = await http.get<ApiResponse<FinancePlan>>(`/finance/plans/${id}`)
    return response.data.data
  },

  async createPlan(payload: FinancePlanPayload): Promise<FinancePlan> {
    const response = await http.post<ApiResponse<FinancePlan>>('/finance/plans', payload)
    return response.data.data
  },

  async updatePlan(id: string, payload: Partial<FinancePlanPayload>): Promise<FinancePlan> {
    const response = await http.put<ApiResponse<FinancePlan>>(`/finance/plans/${id}`, payload)
    return response.data.data
  },

  async deletePlan(id: string): Promise<void> {
    await http.delete(`/finance/plans/${id}`)
  },

  async listContracts(params: FinanceContractListParams): Promise<PaginatedResponse<FinanceContract>> {
    const response = await http.get<PaginatedResponse<FinanceContract>>('/finance/contracts', {
      params,
    })
    return response.data
  },

  async getContract(id: string): Promise<FinanceContract> {
    const response = await http.get<ApiResponse<FinanceContract>>(`/finance/contracts/${id}`)
    return response.data.data
  },

  async createContract(payload: FinanceContractPayload): Promise<FinanceContract> {
    const response = await http.post<ApiResponse<FinanceContract>>('/finance/contracts', payload)
    return response.data.data
  },

  async updateContract(
    id: string,
    payload: Partial<FinanceContractPayload>,
  ): Promise<FinanceContract> {
    const response = await http.put<ApiResponse<FinanceContract>>(
      `/finance/contracts/${id}`,
      payload,
    )
    return response.data.data
  },

  async deleteContract(id: string): Promise<void> {
    await http.delete(`/finance/contracts/${id}`)
  },

  async changeContractStatus(
    id: string,
    status: FinanceContractStatus | string,
  ): Promise<FinanceContract> {
    const response = await http.patch<ApiResponse<FinanceContract>>(
      `/finance/contracts/${id}/status`,
      { status },
    )
    return response.data.data
  },

  async listSubscriptions(
    params: FinanceSubscriptionListParams,
  ): Promise<PaginatedResponse<FinanceSubscription>> {
    const response = await http.get<PaginatedResponse<FinanceSubscription>>(
      '/finance/subscriptions',
      { params },
    )
    return response.data
  },

  async getSubscription(id: string): Promise<FinanceSubscription> {
    const response = await http.get<ApiResponse<FinanceSubscription>>(
      `/finance/subscriptions/${id}`,
    )
    return response.data.data
  },

  async cancelSubscription(id: string): Promise<FinanceSubscription> {
    const response = await http.post<ApiResponse<FinanceSubscription>>(
      `/finance/subscriptions/${id}/cancel`,
    )
    return response.data.data
  },

  async reactivateSubscription(id: string): Promise<FinanceSubscription> {
    const response = await http.post<ApiResponse<FinanceSubscription>>(
      `/finance/subscriptions/${id}/reactivate`,
    )
    return response.data.data
  },

  async listReceivables(
    params: FinanceReceivableListParams,
  ): Promise<PaginatedResponse<FinanceReceivable>> {
    const response = await http.get<PaginatedResponse<FinanceReceivable>>('/finance/receivables', {
      params,
    })
    return response.data
  },

  async getReceivable(id: string): Promise<FinanceReceivable> {
    const response = await http.get<ApiResponse<FinanceReceivable>>(`/finance/receivables/${id}`)
    return response.data.data
  },

  async generateReceivable(payload: GenerateReceivablePayload): Promise<FinanceReceivable> {
    const response = await http.post<ApiResponse<FinanceReceivable>>('/finance/receivables', payload)
    return response.data.data
  },

  async chargeReceivable(
    id: string,
    payload: ChargeReceivablePayload,
  ): Promise<FinanceReceivable> {
    const response = await http.post<ApiResponse<FinanceReceivable>>(
      `/finance/receivables/${id}/charge`,
      payload,
    )
    return response.data.data
  },

  async cancelReceivable(id: string): Promise<FinanceReceivable> {
    const response = await http.post<ApiResponse<FinanceReceivable>>(
      `/finance/receivables/${id}/cancel`,
    )
    return response.data.data
  },

  async markReceivableReceived(
    id: string,
    paid_amount_cents?: number | null,
  ): Promise<FinanceReceivable> {
    const response = await http.post<ApiResponse<FinanceReceivable>>(
      `/finance/receivables/${id}/mark-received`,
      paid_amount_cents != null ? { paid_amount_cents } : {},
    )
    return response.data.data
  },

  async getAsaasConfig(): Promise<TenantAsaasConfig | null> {
    const response = await http.get<ApiResponse<TenantAsaasConfig | null>>('/finance/asaas-config')
    return response.data.data
  },

  async updateAsaasConfig(payload: AsaasConfigPayload): Promise<TenantAsaasConfig> {
    const response = await http.put<ApiResponse<TenantAsaasConfig>>(
      '/finance/asaas-config',
      payload,
    )
    return response.data.data
  },

  async dashboard(): Promise<FinanceDashboardMetrics> {
    const response = await http.get<ApiResponse<FinanceDashboardMetrics>>('/finance/dashboard')
    return response.data.data
  },

  async reports(params: FinanceReportParams): Promise<FinanceReportResult> {
    const response = await http.get<ApiResponse<FinanceReportResult>>('/finance/reports', {
      params,
    })
    return response.data.data
  },

  async portalSubscription(): Promise<FinanceSubscription | null> {
    const response = await http.get<ApiResponse<FinanceSubscription | null>>(
      '/finance/portal/subscription',
    )
    return response.data.data
  },

  async portalReceivables(
    params: FinanceReceivableListParams,
  ): Promise<PaginatedResponse<FinanceReceivable>> {
    const response = await http.get<PaginatedResponse<FinanceReceivable>>(
      '/finance/portal/receivables',
      { params },
    )
    return response.data
  },

  async portalReceivable(id: string): Promise<FinanceReceivable> {
    const response = await http.get<ApiResponse<FinanceReceivable>>(
      `/finance/portal/receivables/${id}`,
    )
    return response.data.data
  },

  async portalPay(id: string, payload: ChargeReceivablePayload): Promise<FinanceReceivable> {
    const response = await http.post<ApiResponse<FinanceReceivable>>(
      `/finance/portal/receivables/${id}/pay`,
      payload,
    )
    return response.data.data
  },
}
