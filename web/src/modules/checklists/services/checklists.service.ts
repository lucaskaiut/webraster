import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type {
  Checklist,
  ChecklistAnswerType,
  ChecklistPhotoRule,
  ChecklistType,
} from '@/shared/types/models'

export interface ChecklistListParams extends ListParams {
  type?: string
  is_active?: boolean
}

export interface ChecklistQuestionPayload {
  prompt: string
  answer_type: ChecklistAnswerType
  photo_rule: ChecklistPhotoRule
  options?: Array<{ label: string }>
}

export interface ChecklistPayload {
  name: string
  description?: string | null
  type: ChecklistType
  is_active: boolean
  questions: ChecklistQuestionPayload[]
}

export const checklistsService = {
  async list(params: ChecklistListParams): Promise<PaginatedResponse<Checklist>> {
    const response = await http.get<PaginatedResponse<Checklist>>('/checklists', { params })

    return response.data
  },

  async get(id: string): Promise<Checklist> {
    const response = await http.get<ApiResponse<Checklist>>(`/checklists/${id}`)

    return response.data.data
  },

  async create(payload: ChecklistPayload): Promise<Checklist> {
    const response = await http.post<ApiResponse<Checklist>>('/checklists', payload)

    return response.data.data
  },

  async update(id: string, payload: ChecklistPayload): Promise<Checklist> {
    const response = await http.put<ApiResponse<Checklist>>(`/checklists/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/checklists/${id}`)
  },
}
