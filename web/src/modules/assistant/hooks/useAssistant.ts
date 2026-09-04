import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { ListParams } from '@/shared/types/api'
import { assistantService } from '../services/assistant.service'

export function useConversationsQuery(params: ListParams & { search?: string }) {
  return useQuery({
    queryKey: queryKeys.assistant.conversations(params),
    queryFn: () => assistantService.listConversations(params),
    placeholderData: keepPreviousData,
  })
}

export function useConversationQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.assistant.conversation(id ?? ''),
    queryFn: () => assistantService.getConversation(id!),
    enabled: !!id,
  })
}

export function useCreateConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => assistantService.createConversation(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.assistant.all })
    },
  })
}

export function useRenameConversation(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (title: string) => assistantService.renameConversation(id, title),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.assistant.all })
    },
  })
}

export function useDeleteConversation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => assistantService.deleteConversation(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.assistant.all })
      toast.success('Conversa removida', 'A conversa foi excluída.')
    },
  })
}
