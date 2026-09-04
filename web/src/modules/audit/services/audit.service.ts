import { http } from '@/shared/api/http'
import type { PaginatedResponse } from '@/shared/types/api'
import type { AuditLog } from '@/shared/types/models'

export const auditService = {
  async list(params: { page?: number; per_page?: number; action?: string }): Promise<PaginatedResponse<AuditLog>> {
    const response = await http.get<PaginatedResponse<AuditLog>>('/audit', { params })

    return response.data
  },
}
