import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Client, ClientContract, ClientOrder, User } from '@/shared/types/models'

export interface ClientPayload {
  name: string
  legal_name?: string | null
  trade_name?: string | null
  document: string
  state_registration?: string | null
  email?: string | null
  financial_email?: string | null
  phone?: string | null
  street?: string | null
  number?: string | null
  complement?: string | null
  neighborhood?: string | null
  city?: string | null
  state?: string | null
  zip?: string | null
  is_active?: boolean
}

export interface ClientUserPayload {
  name: string
  email: string
  password: string
  role_ids?: number[]
}

export interface ClientOrderItemPayload {
  service_id: string
  vehicle_ids: string[]
}

export interface ClientOrderPayload {
  due_day?: number
  periodicity?: string
  next_billing_at?: string | null
  items: ClientOrderItemPayload[]
}

export interface ClientContractPayload {
  contract_id: string
  valid_until: string
}

export const clientsService = {
  async list(params: ListParams): Promise<PaginatedResponse<Client>> {
    const response = await http.get<PaginatedResponse<Client>>('/clients', { params })

    return response.data
  },

  async get(id: string): Promise<Client> {
    const response = await http.get<ApiResponse<Client>>(`/clients/${id}`)

    return response.data.data
  },

  async create(payload: ClientPayload): Promise<Client> {
    const response = await http.post<ApiResponse<Client>>('/clients', payload)

    return response.data.data
  },

  async update(id: string, payload: ClientPayload): Promise<Client> {
    const response = await http.put<ApiResponse<Client>>(`/clients/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/clients/${id}`)
  },

  async listUsers(clientId: string, params: ListParams): Promise<PaginatedResponse<User>> {
    const response = await http.get<PaginatedResponse<User>>(`/clients/${clientId}/users`, {
      params,
    })

    return response.data
  },

  async createUser(clientId: string, payload: ClientUserPayload): Promise<User> {
    const response = await http.post<ApiResponse<User>>(`/clients/${clientId}/users`, payload)

    return response.data.data
  },

  async removeUser(clientId: string, userId: string): Promise<void> {
    await http.delete(`/clients/${clientId}/users/${userId}`)
  },

  async getOrder(clientId: string): Promise<ClientOrder | null> {
    const response = await http.get<ApiResponse<ClientOrder | null>>(`/clients/${clientId}/order`)

    return response.data.data
  },

  async upsertOrder(clientId: string, payload: ClientOrderPayload): Promise<ClientOrder> {
    const response = await http.put<ApiResponse<ClientOrder>>(`/clients/${clientId}/order`, payload)

    return response.data.data
  },

  async getContract(clientId: string): Promise<ClientContract | null> {
    const response = await http.get<ApiResponse<ClientContract | null>>(`/clients/${clientId}/contract`)

    return response.data.data
  },

  async upsertContract(clientId: string, payload: ClientContractPayload): Promise<ClientContract> {
    const response = await http.put<ApiResponse<ClientContract>>(`/clients/${clientId}/contract`, payload)

    return response.data.data
  },
}
