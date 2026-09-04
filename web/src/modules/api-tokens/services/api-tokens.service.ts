import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { ApiToken } from '@/shared/types/models'

export interface ApiTokenPayload {
  name: string
  expires_at?: string | null
  permissions?: string[] | null
}

export interface IssuedApiToken {
  token: string
  api_token: ApiToken
}

export const apiTokensService = {
  async list(): Promise<ApiToken[]> {
    const response = await http.get<ApiResponse<ApiToken[]>>('/api-tokens')

    return response.data.data
  },

  async create(payload: ApiTokenPayload): Promise<IssuedApiToken> {
    const response = await http.post<ApiResponse<IssuedApiToken>>('/api-tokens', payload)

    return response.data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/api-tokens/${id}`)
  },
}
