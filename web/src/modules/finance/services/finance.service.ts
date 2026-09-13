import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  BillingPeriodicity,
  FinanceBilling,
  FinanceClientOverview,
  FinanceDashboardMetrics,
  FinancePaymentMethod,
  FinancePlan,
  FinanceReportResult,
  FinanceReportType,
  FinanceSubscription,
  PaymentGatewayConfig,
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

export interface FinanceSubscriptionListParams extends ListParams {
  status?: string
  client_id?: string
}

export interface AssignFinanceSubscriptionPayload {
  client_id: string
  plan_id: string
  due_day?: number
  block_on_overdue?: boolean
  block_after_days?: number
  next_billing_at?: string | null
}

export interface UpdateFinanceSubscriptionPayload {
  next_billing_at?: string | null
  due_day?: number
  block_on_overdue?: boolean
  block_after_days?: number
}

export interface FinanceBillingListParams extends ListParams {
  status?: string
  client_id?: string
  subscription_id?: string
  due_from?: string
  due_to?: string
}

export interface GenerateBillingPayload {
  subscription_id: string
  due_at?: string | null
}

export interface ChargeBillingPayload {
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

export interface PaymentGatewayConfigPayload {
  gateway: string
  is_active?: boolean
  credentials: Record<string, string>
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

  async assignSubscription(
    payload: AssignFinanceSubscriptionPayload,
  ): Promise<FinanceSubscription> {
    const response = await http.post<ApiResponse<FinanceSubscription>>(
      '/finance/subscriptions/assign',
      payload,
    )
    return response.data.data
  },

  async updateSubscription(
    id: string,
    payload: UpdateFinanceSubscriptionPayload,
  ): Promise<FinanceSubscription> {
    const response = await http.patch<ApiResponse<FinanceSubscription>>(
      `/finance/subscriptions/${id}`,
      payload,
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

  async clientOverview(clientId: string): Promise<FinanceClientOverview> {
    const response = await http.get<ApiResponse<FinanceClientOverview>>(
      `/finance/clients/${clientId}/overview`,
    )
    return response.data.data
  },

  async listBillings(
    params: FinanceBillingListParams,
  ): Promise<PaginatedResponse<FinanceBilling>> {
    const response = await http.get<PaginatedResponse<FinanceBilling>>('/finance/billings', {
      params,
    })
    return response.data
  },

  async getBilling(id: string): Promise<FinanceBilling> {
    const response = await http.get<ApiResponse<FinanceBilling>>(`/finance/billings/${id}`)
    return response.data.data
  },

  async generateBilling(payload: GenerateBillingPayload): Promise<FinanceBilling> {
    const response = await http.post<ApiResponse<FinanceBilling>>('/finance/billings', payload)
    return response.data.data
  },

  async chargeBilling(id: string, payload: ChargeBillingPayload): Promise<FinanceBilling> {
    const response = await http.post<ApiResponse<FinanceBilling>>(
      `/finance/billings/${id}/charge`,
      payload,
    )
    return response.data.data
  },

  async cancelBilling(id: string): Promise<FinanceBilling> {
    const response = await http.post<ApiResponse<FinanceBilling>>(
      `/finance/billings/${id}/cancel`,
    )
    return response.data.data
  },

  async markBillingPaid(
    id: string,
    paid_amount_cents?: number | null,
  ): Promise<FinanceBilling> {
    const response = await http.post<ApiResponse<FinanceBilling>>(
      `/finance/billings/${id}/mark-paid`,
      paid_amount_cents != null ? { paid_amount_cents } : {},
    )
    return response.data.data
  },

  async getPaymentGatewayConfig(gateway?: string): Promise<PaymentGatewayConfig> {
    const response = await http.get<ApiResponse<PaymentGatewayConfig>>(
      '/finance/payment-gateway-config',
      { params: gateway ? { gateway } : undefined },
    )
    return response.data.data
  },

  async updatePaymentGatewayConfig(
    payload: PaymentGatewayConfigPayload,
  ): Promise<PaymentGatewayConfig> {
    const response = await http.put<ApiResponse<PaymentGatewayConfig>>(
      '/finance/payment-gateway-config',
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

  async portalBillings(
    params: FinanceBillingListParams,
  ): Promise<PaginatedResponse<FinanceBilling>> {
    const response = await http.get<PaginatedResponse<FinanceBilling>>(
      '/finance/portal/billings',
      { params },
    )
    return response.data
  },

  async portalBilling(id: string): Promise<FinanceBilling> {
    const response = await http.get<ApiResponse<FinanceBilling>>(
      `/finance/portal/billings/${id}`,
    )
    return response.data.data
  },

  async portalPay(id: string, payload: ChargeBillingPayload): Promise<FinanceBilling> {
    const response = await http.post<ApiResponse<FinanceBilling>>(
      `/finance/portal/billings/${id}/pay`,
      payload,
    )
    return response.data.data
  },
}
