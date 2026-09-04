import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { Webhook, WebhookLog } from '@/shared/types/models'

export interface WebhookPayload {
  name: string
  url: string
  method?: string
  event: string
  headers?: Record<string, string> | null
  query_params?: Record<string, string> | null
  body_template?: Record<string, unknown> | null
  is_active?: boolean
  secret?: string | null
  description?: string | null
}

export interface WebhookEventOption {
  value: string
  label: string
}

export const webhooksService = {
  async list(): Promise<Webhook[]> {
    const response = await http.get<ApiResponse<Webhook[]>>('/webhooks')

    return response.data.data
  },

  async create(payload: WebhookPayload): Promise<Webhook> {
    const response = await http.post<ApiResponse<Webhook>>('/webhooks', payload)

    return response.data.data
  },

  async get(id: number): Promise<Webhook> {
    const response = await http.get<ApiResponse<Webhook>>(`/webhooks/${id}`)

    return response.data.data
  },

  async update(id: number, payload: Partial<WebhookPayload>): Promise<Webhook> {
    const response = await http.patch<ApiResponse<Webhook>>(`/webhooks/${id}`, payload)

    return response.data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/webhooks/${id}`)
  },

  async logs(id: number): Promise<WebhookLog[]> {
    const response = await http.get<ApiResponse<WebhookLog[]>>(`/webhooks/${id}/logs`)

    return response.data.data
  },

  async events(): Promise<WebhookEventOption[]> {
    const response = await http.get<ApiResponse<WebhookEventOption[]>>('/webhooks/events')

    return response.data.data
  },
}
