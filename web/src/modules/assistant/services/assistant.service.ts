import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Conversation, ConversationSummary } from '@/shared/types/models'

export const assistantService = {
  async listConversations(params: ListParams & { search?: string }): Promise<PaginatedResponse<ConversationSummary>> {
    const response = await http.get<PaginatedResponse<ConversationSummary>>('/assistant/conversations', { params })

    return response.data
  },

  async createConversation(): Promise<Conversation> {
    const response = await http.post<ApiResponse<Conversation>>('/assistant/conversations')

    return response.data.data
  },

  async getConversation(id: string): Promise<Conversation> {
    const response = await http.get<ApiResponse<Conversation>>(`/assistant/conversations/${id}`)

    return response.data.data
  },

  async renameConversation(id: string, title: string): Promise<Conversation> {
    const response = await http.patch<ApiResponse<Conversation>>(`/assistant/conversations/${id}`, { title })

    return response.data.data
  },

  async deleteConversation(id: string): Promise<void> {
    await http.delete(`/assistant/conversations/${id}`)
  },
}
