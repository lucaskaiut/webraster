import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { Invoice, PaymentGatewayOption, Plan, Subscription } from '@/shared/types/models'

export interface PlanPayload {
  name: string
  description?: string | null
  price: number | string
  recurrence_value: number
  recurrence_unit: 'days' | 'weeks' | 'months' | 'years'
  free_trial_days?: number
  active?: boolean
}

export const billingService = {
  async catalog(): Promise<Plan[]> {
    const response = await http.get<ApiResponse<Plan[]>>('/billing/plans/catalog')

    return response.data.data
  },

  async listPlans(): Promise<{ data: Plan[]; meta: PaginatedResponse<Plan>['meta'] }> {
    const response = await http.get<PaginatedResponse<Plan>>('/billing/plans')

    return {
      data: response.data.data,
      meta: response.data.meta,
    }
  },

  async getPlan(id: string): Promise<Plan> {
    const response = await http.get<ApiResponse<Plan>>(`/billing/plans/${id}`)

    return response.data.data
  },

  async createPlan(payload: PlanPayload): Promise<Plan> {
    const response = await http.post<ApiResponse<Plan>>('/billing/plans', payload)

    return response.data.data
  },

  async updatePlan(id: string, payload: Partial<PlanPayload>): Promise<Plan> {
    const response = await http.patch<ApiResponse<Plan>>(`/billing/plans/${id}`, payload)

    return response.data.data
  },

  async deactivatePlan(id: string): Promise<Plan> {
    const response = await http.delete<ApiResponse<Plan>>(`/billing/plans/${id}`)

    return response.data.data
  },

  async getSubscription(): Promise<Subscription | null> {
    const response = await http.get<ApiResponse<Subscription | null>>('/billing/subscription')

    return response.data.data
  },

  async createSubscription(planId: string, paymentGateway?: string | null): Promise<Subscription> {
    const response = await http.post<ApiResponse<Subscription>>('/billing/subscription', {
      plan_id: planId,
      ...(paymentGateway ? { payment_gateway: paymentGateway } : {}),
    })

    return response.data.data
  },

  async listGateways(): Promise<PaymentGatewayOption[]> {
    const response = await http.get<ApiResponse<PaymentGatewayOption[]>>('/billing/gateways')

    return response.data.data
  },

  async changePlan(planId: string, paymentGateway?: string): Promise<Subscription> {
    const response = await http.post<ApiResponse<Subscription>>('/billing/subscription/change-plan', {
      plan_id: planId,
      ...(paymentGateway ? { payment_gateway: paymentGateway } : {}),
    })

    return response.data.data
  },

  async cancelSubscription(): Promise<Subscription> {
    const response = await http.post<ApiResponse<Subscription>>('/billing/subscription/cancel')

    return response.data.data
  },

  async reactivateSubscription(): Promise<Subscription> {
    const response = await http.post<ApiResponse<Subscription>>('/billing/subscription/reactivate')

    return response.data.data
  },

  async listInvoices(): Promise<{ data: Invoice[]; meta: PaginatedResponse<Invoice>['meta'] }> {
    const response = await http.get<PaginatedResponse<Invoice>>('/billing/invoices')

    return {
      data: response.data.data,
      meta: response.data.meta,
    }
  },

  async getInvoice(id: string): Promise<Invoice> {
    const response = await http.get<ApiResponse<Invoice>>(`/billing/invoices/${id}`)

    return response.data.data
  },

  async payInvoice(
    id: string,
    paymentGateway: string,
    paymentData: Record<string, unknown> = {},
  ): Promise<Invoice> {
    const response = await http.post<ApiResponse<Invoice>>(`/billing/invoices/${id}/pay`, {
      payment_gateway: paymentGateway,
      payment_data: paymentData,
    })

    return response.data.data
  },
}
