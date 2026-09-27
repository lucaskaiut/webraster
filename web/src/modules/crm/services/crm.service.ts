import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  CrmAiConfiguration,
  CrmConversation,
  CrmLead,
  CrmMessage,
  CrmPipeline,
  CrmPipelineInput,
  CrmPipelineStage,
  CrmPipelineStageInput,
  EvolutionBootstrapPayload,
  EvolutionConnectPayload,
  MessagingConnection,
} from '../lib/types'

export const crmService = {
  async listPipelines(): Promise<CrmPipeline[]> {
    const response = await http.get<ApiResponse<CrmPipeline[]>>('/crm/pipelines')
    return response.data.data ?? []
  },

  async createPipeline(payload: CrmPipelineInput): Promise<CrmPipeline> {
    const response = await http.post<ApiResponse<CrmPipeline>>('/crm/pipelines', payload)
    return response.data.data!
  },

  async updatePipeline(pipelineId: string, payload: Partial<CrmPipelineInput>): Promise<CrmPipeline> {
    const response = await http.patch<ApiResponse<CrmPipeline>>(`/crm/pipelines/${pipelineId}`, payload)
    return response.data.data!
  },

  async createStage(pipelineId: string, payload: CrmPipelineStageInput): Promise<CrmPipelineStage> {
    const response = await http.post<ApiResponse<CrmPipelineStage>>(
      `/crm/pipelines/${pipelineId}/stages`,
      payload,
    )
    return response.data.data!
  },

  async updateStage(
    pipelineId: string,
    stageId: string,
    payload: CrmPipelineStageInput,
  ): Promise<CrmPipelineStage> {
    const response = await http.patch<ApiResponse<CrmPipelineStage>>(
      `/crm/pipelines/${pipelineId}/stages/${stageId}`,
      payload,
    )
    return response.data.data!
  },

  async reorderStages(pipelineId: string, stageIds: string[]): Promise<CrmPipeline> {
    const response = await http.post<ApiResponse<CrmPipeline>>(
      `/crm/pipelines/${pipelineId}/stages/reorder`,
      { stage_ids: stageIds },
    )
    return response.data.data!
  },

  async listLeads(params?: ListParams & { pipeline_id?: string; stage_id?: string; search?: string }): Promise<PaginatedResponse<CrmLead>> {
    const response = await http.get<PaginatedResponse<CrmLead>>('/crm/leads', { params })
    return response.data
  },

  async getLead(leadId: string): Promise<CrmLead> {
    const response = await http.get<ApiResponse<CrmLead>>(`/crm/leads/${leadId}`)
    return response.data.data!
  },

  async kanban(pipelineId: string): Promise<Record<string, CrmLead[]>> {
    const response = await http.get<ApiResponse<Record<string, CrmLead[]>>>('/crm/leads/kanban', {
      params: { pipeline_id: pipelineId },
    })
    return response.data.data ?? {}
  },

  async moveLeadStage(leadId: string, stageId: string): Promise<CrmLead> {
    const response = await http.patch<ApiResponse<CrmLead>>(`/crm/leads/${leadId}/stage`, {
      stage_id: stageId,
    })
    return response.data.data!
  },

  async listConversations(params?: ListParams & { search?: string; filter?: string }): Promise<PaginatedResponse<CrmConversation>> {
    const response = await http.get<PaginatedResponse<CrmConversation>>('/crm/conversations', { params })
    return response.data
  },

  async getConversation(id: string): Promise<CrmConversation> {
    const response = await http.get<ApiResponse<CrmConversation>>(`/crm/conversations/${id}`)
    return response.data.data!
  },

  async listMessages(conversationId: string, params?: ListParams): Promise<PaginatedResponse<CrmMessage>> {
    const response = await http.get<PaginatedResponse<CrmMessage>>(`/crm/conversations/${conversationId}/messages`, {
      params,
    })
    return response.data
  },

  async sendMessage(conversationId: string, text: string): Promise<CrmMessage> {
    const response = await http.post<ApiResponse<CrmMessage>>(`/crm/conversations/${conversationId}/messages`, { text })
    return response.data.data!
  },

  async markConversationRead(conversationId: string): Promise<void> {
    await http.post(`/crm/conversations/${conversationId}/read`)
  },

  async setAiMode(conversationId: string, mode: 'ai_active' | 'ai_disabled' | 'human_handoff'): Promise<void> {
    await http.patch(`/crm/conversations/${conversationId}/ai-mode`, { mode })
  },

  async listConnections(): Promise<MessagingConnection[]> {
    const response = await http.get<ApiResponse<MessagingConnection[]>>('/crm/messaging/connections')
    return response.data.data ?? []
  },

  async createConnection(payload: {
    provider: string
    name: string
    base_url?: string
    instance_name?: string
    credentials?: { api_key?: string; webhook_secret?: string }
  }): Promise<MessagingConnection> {
    const response = await http.post<ApiResponse<MessagingConnection>>('/crm/messaging/connections', payload)
    return response.data.data!
  },

  async evolutionBootstrap(payload: EvolutionBootstrapPayload = {}): Promise<{
    connection: MessagingConnection
    connect: EvolutionConnectPayload
  }> {
    const response = await http.post<
      ApiResponse<{ connection: MessagingConnection; connect: EvolutionConnectPayload }>
    >('/crm/messaging/evolution/bootstrap', payload)
    return response.data.data!
  },

  async evolutionConnect(connectionId: string): Promise<{
    connection: MessagingConnection
    connect: EvolutionConnectPayload
  }> {
    const response = await http.post<
      ApiResponse<{ connection: MessagingConnection; connect: EvolutionConnectPayload }>
    >(`/crm/messaging/connections/${connectionId}/evolution/connect`)
    return response.data.data!
  },

  async evolutionState(connectionId: string): Promise<{
    connection: MessagingConnection
    connect: EvolutionConnectPayload
  }> {
    const response = await http.get<
      ApiResponse<{ connection: MessagingConnection; connect: EvolutionConnectPayload }>
    >(`/crm/messaging/connections/${connectionId}/evolution/state`)
    return response.data.data!
  },

  async getAiConfiguration(): Promise<CrmAiConfiguration> {
    const response = await http.get<ApiResponse<CrmAiConfiguration>>('/crm/ai/configuration')
    return response.data.data!
  },

  async updateAiConfiguration(
    payload: Partial<CrmAiConfiguration> & { api_key?: string },
  ): Promise<CrmAiConfiguration> {
    const response = await http.patch<ApiResponse<CrmAiConfiguration>>('/crm/ai/configuration', payload)
    return response.data.data!
  },
}
