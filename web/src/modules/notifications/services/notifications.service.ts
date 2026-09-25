import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { AppNotification, NotificationLog } from '@/shared/types/models'

export interface SendNotificationPayload {
  title: string
  body: string
  audience: 'tenant' | 'client' | 'users'
  client_id?: string | null
  user_ids?: string[]
  data?: Record<string, unknown> | null
  push?: boolean
}

export interface SentNotificationsParams extends ListParams {
  source?: string
  type?: string
  user_id?: string
  clicked?: boolean
  from?: string
  to?: string
}

export const notificationsService = {
  async list(params: ListParams & { unread?: boolean }): Promise<PaginatedResponse<AppNotification>> {
    const response = await http.get<PaginatedResponse<AppNotification>>('/notifications', { params })
    return response.data
  },

  async unreadCount(): Promise<number> {
    const response = await http.get<ApiResponse<{ count: number }>>('/notifications/unread-count')
    return response.data.data.count
  },

  async markRead(id: string): Promise<AppNotification> {
    const response = await http.post<ApiResponse<AppNotification>>(`/notifications/${id}/read`)
    return response.data.data
  },

  async markAllRead(): Promise<void> {
    await http.post('/notifications/read-all')
  },

  /** Notificação manual do painel (in-app + push). */
  async send(payload: SendNotificationPayload): Promise<{ recipients: number }> {
    const response = await http.post<ApiResponse<{ recipients: number }>>('/notifications/send', payload)
    return response.data.data
  },

  /** Histórico de notificações enviadas (log por destinatário/canal). */
  async sent(params: SentNotificationsParams): Promise<PaginatedResponse<NotificationLog>> {
    const response = await http.get<PaginatedResponse<NotificationLog>>('/notifications/sent', { params })
    return response.data
  },
}
