import { http } from '@/shared/api/http'
import { ensureCsrfCookie } from '@/shared/api/csrf'
import type { ApiResponse } from '@/shared/types/api'
import type { Invoice, Plan, Tenant, User, AvailableTenant } from '@/shared/types/models'
import type { PaymentMethodOption } from '../store/register-checkout.store'

export interface CheckoutRegisterPayload {
  company: {
    name: string
    document: string
    phone: string
  }
  user: {
    name: string
    email: string
    password: string
  }
  subscription: {
    plan_id: string
  }
  payment?: {
    method: string
    data: Record<string, unknown>
  }
}

export interface CheckoutRegisterResult {
  token: string | null
  token_type: string
  user: User
  tenant: Tenant
  is_master: boolean
  available_tenants: AvailableTenant[]
  requires_payment: boolean
  is_trial: boolean
  trial_days: number
  billing_status: 'none' | 'trialing' | 'pending_payment' | string
  payment_methods: PaymentMethodOption[]
  invoice: Invoice | null
}

export const checkoutService = {
  async listPublicPlans(): Promise<Plan[]> {
    const response = await http.get<ApiResponse<Plan[]>>('/plans/public')

    return response.data.data
  },

  async listPaymentMethods(): Promise<PaymentMethodOption[]> {
    const response = await http.get<ApiResponse<PaymentMethodOption[]>>('/payment-methods')

    return response.data.data
  },

  async register(payload: CheckoutRegisterPayload): Promise<CheckoutRegisterResult> {
    await ensureCsrfCookie()
    const response = await http.post<ApiResponse<CheckoutRegisterResult>>('/auth/register', payload)

    return response.data.data
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
    await ensureCsrfCookie()
    const response = await http.post<ApiResponse<Invoice>>(`/billing/invoices/${id}/pay`, {
      payment_gateway: paymentGateway,
      payment_data: paymentData,
    })

    return response.data.data
  },
}
