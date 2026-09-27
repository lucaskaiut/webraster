import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { crmService } from '../services/crm.service'

export function useCrmPipelinesQuery() {
  return useQuery({
    queryKey: queryKeys.crm.pipelines(),
    queryFn: () => crmService.listPipelines(),
  })
}

export function useCrmConversationsQuery(params?: { search?: string; filter?: string }) {
  return useQuery({
    queryKey: queryKeys.crm.conversations(params),
    queryFn: () => crmService.listConversations(params),
  })
}

export function useCrmMessagesQuery(conversationId: string | null) {
  return useQuery({
    queryKey: queryKeys.crm.messages(conversationId ?? ''),
    queryFn: () => crmService.listMessages(conversationId!),
    enabled: Boolean(conversationId),
  })
}

export function useCrmKanbanQuery(pipelineId: string | null) {
  return useQuery({
    queryKey: queryKeys.crm.kanban(pipelineId ?? ''),
    queryFn: () => crmService.kanban(pipelineId!),
    enabled: Boolean(pipelineId),
  })
}

export function useCrmLeadsQuery(params?: { pipeline_id?: string; search?: string }) {
  return useQuery({
    queryKey: queryKeys.crm.leads(params),
    queryFn: () => crmService.listLeads(params),
  })
}

export function useCrmLeadQuery(leadId: string | null) {
  return useQuery({
    queryKey: queryKeys.crm.lead(leadId ?? ''),
    queryFn: () => crmService.getLead(leadId!),
    enabled: Boolean(leadId),
  })
}

export function useSendCrmMessage() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: ({ conversationId, text }: { conversationId: string; text: string }) =>
      crmService.sendMessage(conversationId, text),
    onSuccess: (_, { conversationId }) => {
      void client.invalidateQueries({ queryKey: queryKeys.crm.messages(conversationId) })
      void client.invalidateQueries({ queryKey: queryKeys.crm.conversations() })
    },
  })
}

export function useMoveCrmLeadStage() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: ({ leadId, stageId }: { leadId: string; stageId: string }) =>
      crmService.moveLeadStage(leadId, stageId),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.crm.all })
    },
  })
}
