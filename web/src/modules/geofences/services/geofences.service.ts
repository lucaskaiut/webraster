import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Geofence, GeofenceEvent, Poi, PoiCategory } from '@/shared/types/models'

export interface GeofenceListParams extends ListParams {
  client_id?: string
  type?: 'circle' | 'polygon'
  is_active?: boolean
}

export interface GeofencePayload {
  client_id: string
  name: string
  description?: string | null
  type: 'circle' | 'polygon'
  is_active?: boolean
  center_latitude?: number | null
  center_longitude?: number | null
  radius_meters?: number | null
  geometry?: Array<{ latitude: number; longitude: number }> | null
}

export interface GeofenceEventListParams extends ListParams {
  vehicle_id?: string
  geofence_id?: string
  client_id?: string
  type?: 'entry' | 'exit'
  from?: string
  to?: string
}

export interface PoiListParams extends ListParams {
  client_id?: string
  category_id?: string
  is_active?: boolean
}

export interface PoiPayload {
  client_id: string
  poi_category_id: string
  name: string
  description?: string | null
  latitude: number
  longitude: number
  address?: string | null
  is_active?: boolean
}

export const geofencesService = {
  async list(params: GeofenceListParams): Promise<PaginatedResponse<Geofence>> {
    const response = await http.get<PaginatedResponse<Geofence>>('/geofences', { params })
    return response.data
  },

  async map(params?: { client_id?: string; is_active?: boolean }): Promise<Geofence[]> {
    const response = await http.get<ApiResponse<Geofence[]>>('/geofences/map', { params })
    return response.data.data
  },

  async get(id: string): Promise<Geofence> {
    const response = await http.get<ApiResponse<Geofence>>(`/geofences/${id}`)
    return response.data.data
  },

  async create(payload: GeofencePayload): Promise<Geofence> {
    const response = await http.post<ApiResponse<Geofence>>('/geofences', payload)
    return response.data.data
  },

  async update(id: string, payload: Partial<GeofencePayload>): Promise<Geofence> {
    const response = await http.put<ApiResponse<Geofence>>(`/geofences/${id}`, payload)
    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/geofences/${id}`)
  },
}

export const geofenceEventsService = {
  async list(params: GeofenceEventListParams): Promise<PaginatedResponse<GeofenceEvent>> {
    const response = await http.get<PaginatedResponse<GeofenceEvent>>('/geofence-events', { params })
    return response.data
  },
}

export const poisService = {
  async list(params: PoiListParams): Promise<PaginatedResponse<Poi>> {
    const response = await http.get<PaginatedResponse<Poi>>('/pois', { params })
    return response.data
  },

  async map(params?: { client_id?: string; category_id?: string; is_active?: boolean }): Promise<Poi[]> {
    const response = await http.get<ApiResponse<Poi[]>>('/pois/map', { params })
    return response.data.data
  },

  async categories(): Promise<PoiCategory[]> {
    const response = await http.get<ApiResponse<PoiCategory[]>>('/poi-categories')
    return response.data.data
  },

  async get(id: string): Promise<Poi> {
    const response = await http.get<ApiResponse<Poi>>(`/pois/${id}`)
    return response.data.data
  },

  async create(payload: PoiPayload): Promise<Poi> {
    const response = await http.post<ApiResponse<Poi>>('/pois', payload)
    return response.data.data
  },

  async update(id: string, payload: Partial<PoiPayload>): Promise<Poi> {
    const response = await http.put<ApiResponse<Poi>>(`/pois/${id}`, payload)
    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/pois/${id}`)
  },
}
