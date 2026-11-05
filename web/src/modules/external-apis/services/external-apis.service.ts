import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type {
  ExternalApiMetrics,
  ExternalApiMetricsParams,
  ExternalApiRequest,
  ExternalApiRequestDetail,
  ExternalApiRequestsParams,
  ExternalApiService,
} from '../types'

export const externalApisService = {
  async services(): Promise<ExternalApiService[]> {
    const response = await http.get<ApiResponse<ExternalApiService[]>>('/external-apis/services')

    return response.data.data
  },

  async metrics(params: ExternalApiMetricsParams): Promise<ExternalApiMetrics> {
    const response = await http.get<ApiResponse<ExternalApiMetrics>>('/external-apis/metrics', { params })

    return response.data.data
  },

  async requests(params: ExternalApiRequestsParams): Promise<PaginatedResponse<ExternalApiRequest>> {
    const response = await http.get<PaginatedResponse<ExternalApiRequest>>('/external-apis/requests', { params })

    return response.data
  },

  async request(id: number): Promise<ExternalApiRequestDetail> {
    const response = await http.get<ApiResponse<ExternalApiRequestDetail>>(`/external-apis/requests/${id}`)

    return response.data.data
  },
}
