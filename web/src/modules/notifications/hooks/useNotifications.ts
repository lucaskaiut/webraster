import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { notificationsService } from '../services/notifications.service'

export function useNotificationsQuery(params: { page?: number; per_page?: number; unread?: boolean } = {}) {
  return useQuery({
    queryKey: queryKeys.notifications.list(params),
    queryFn: () => notificationsService.list(params),
    refetchInterval: 15_000,
  })
}

export function useUnreadNotificationsCount(enabled = true) {
  return useQuery({
    queryKey: queryKeys.notifications.unreadCount(),
    queryFn: () => notificationsService.unreadCount(),
    enabled,
    refetchInterval: 15_000,
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => notificationsService.markRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all })
    },
  })
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => notificationsService.markAllRead(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all })
    },
  })
}
