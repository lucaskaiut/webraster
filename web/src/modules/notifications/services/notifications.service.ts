import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { AppNotification } from '@/shared/types/models'

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
}
