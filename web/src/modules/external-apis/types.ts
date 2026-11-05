export type Granularity = 'minute' | 'hour' | 'day' | 'week' | 'month'

export interface ExternalApiService {
  key: string
  provider: string
  provider_label: string
  label: string
}

export interface ExternalApiSeries {
  service: string
  label: string
  data: number[]
  errors: number[]
  total: number
  error_total: number
}

export interface ExternalApiMetrics {
  granularity: Granularity
  timezone: string
  from: string
  to: string
  buckets: string[]
  series: ExternalApiSeries[]
  summary: { total: number; errors: number; avg_duration_ms: number | null }
  top_origins: Array<{ origin: string | null; service: string; label: string; total: number }>
}

export interface ExternalApiRequest {
  id: number
  provider: string
  service: string
  service_label: string
  method: string
  url: string
  status_code: number | null
  succeeded: boolean
  duration_ms: number | null
  error: string | null
  origin: string | null
  tenant: { id: number; name: string } | null
  user: { id: number; name: string } | null
  created_at: string | null
}

export interface ExternalApiRequestDetail extends ExternalApiRequest {
  request_headers: Record<string, string>
  request_body: string | null
  response_headers: Record<string, string>
  response_body: string | null
}

export interface ExternalApiPeriodParams {
  from: string
  to: string
  timezone: string
}

export interface ExternalApiMetricsParams extends ExternalApiPeriodParams {
  granularity: Granularity
  services?: string[]
}

export interface ExternalApiRequestsParams extends ExternalApiPeriodParams {
  page?: number
  per_page?: number
  service?: string
  status?: 'success' | 'error'
  search?: string
}
