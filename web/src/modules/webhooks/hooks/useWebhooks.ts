import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { webhooksService, type WebhookPayload } from '../services/webhooks.service'

export function useWebhooksQuery() {
  return useQuery({
    queryKey: queryKeys.webhooks.list(),
    queryFn: webhooksService.list,
  })
}

export function useWebhookQuery(id: number) {
  return useQuery({
    queryKey: queryKeys.webhooks.detail(id),
    queryFn: () => webhooksService.get(id),
    enabled: id > 0,
  })
}

export function useWebhookEventsQuery() {
  return useQuery({
    queryKey: queryKeys.webhooks.events(),
    queryFn: webhooksService.events,
    staleTime: Infinity,
  })
}

export function useCreateWebhook() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: WebhookPayload) => webhooksService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.webhooks.all })
      toast.success('Webhook criado', 'As notificações serão enviadas quando o evento ocorrer.')
    },
  })
}

export function useUpdateWebhook() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...payload }: WebhookPayload & { id: number }) => webhooksService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.webhooks.all })
      toast.success('Webhook atualizado', 'As configurações foram salvas.')
    },
  })
}

export function useDeleteWebhook() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => webhooksService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.webhooks.all })
      toast.success('Webhook removido', 'Ele não será mais disparado.')
    },
  })
}

export function useWebhookLogsQuery(id: number) {
  return useQuery({
    queryKey: queryKeys.webhooks.logs(id),
    queryFn: () => webhooksService.logs(id),
    enabled: id > 0,
  })
}
