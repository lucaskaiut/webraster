import { http } from '@/shared/api/http'
import type { ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Commission } from '@/shared/types/models'

export interface CommissionListParams extends ListParams {
  status?: string
  technician_id?: string
}

export const commissionsService = {
  async list(params: CommissionListParams): Promise<PaginatedResponse<Commission>> {
    const response = await http.get<PaginatedResponse<Commission>>('/commissions', { params })

    return response.data
  },
}
