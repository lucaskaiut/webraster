import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type {
  CommandReport,
  EventReport,
  PositionReport,
  StopReport,
  TripReport,
} from '@/shared/types/models'
import { downloadBlob } from '@/shared/utils/report-export'

export interface CommandReportFilters {
  from?: string
  to?: string
  client_id?: string
  vehicle_id?: string
}

export interface PositionReportFilters {
  from?: string
  to?: string
  client_id?: string
  vehicle_id: string
}

export interface StopReportFilters {
  from?: string
  to?: string
  client_id?: string
}

export interface TripReportFilters {
  from?: string
  to?: string
  client_id?: string
  vehicle_id: string
}

export interface EventReportFilters {
  from?: string
  to?: string
  client_id?: string
  vehicle_id?: string
}

export const reportsService = {
  async commands(filters: CommandReportFilters = {}): Promise<CommandReport> {
    const response = await http.get<ApiResponse<CommandReport>>('/reports/commands', {
      params: filters,
    })
    return response.data.data
  },

  async commandsExport(filters: CommandReportFilters = {}): Promise<void> {
    const response = await http.get<Blob>('/reports/commands/export', {
      params: filters,
      responseType: 'blob',
    })
    downloadBlob(response.data, 'comandos-enviados.xlsx')
  },

  async positions(filters: PositionReportFilters): Promise<PositionReport> {
    const response = await http.get<ApiResponse<PositionReport>>('/reports/positions', {
      params: filters,
    })
    return response.data.data
  },

  async positionsExport(filters: PositionReportFilters): Promise<void> {
    const response = await http.get<Blob>('/reports/positions/export', {
      params: filters,
      responseType: 'blob',
    })
    downloadBlob(response.data, 'historico-posicoes.xlsx')
  },

  async stops(filters: StopReportFilters = {}): Promise<StopReport> {
    const response = await http.get<ApiResponse<StopReport>>('/reports/stops', {
      params: filters,
    })
    return response.data.data
  },

  async stopsExport(filters: StopReportFilters = {}): Promise<void> {
    const response = await http.get<Blob>('/reports/stops/export', {
      params: filters,
      responseType: 'blob',
    })
    downloadBlob(response.data, 'relatorio-paradas.xlsx')
  },

  async trips(filters: TripReportFilters): Promise<TripReport> {
    const response = await http.get<ApiResponse<TripReport>>('/reports/trips', {
      params: filters,
    })
    return response.data.data
  },

  async events(filters: EventReportFilters = {}): Promise<EventReport> {
    const response = await http.get<ApiResponse<EventReport>>('/reports/events', {
      params: filters,
    })
    return response.data.data
  },
}
