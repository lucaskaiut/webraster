import type { ListParams } from '@/shared/types/api'

type ServiceOrderQueryParams = ListParams & {
  status?: string
  type?: string
  priority?: string
  client_id?: string
  vehicle_id?: string
  technician_id?: string
  from?: string
  to?: string
}

export const queryKeys = {
  session: ['session'] as const,

  users: {
    all: ['users'] as const,
    list: (params: ListParams) => ['users', 'list', params] as const,
    detail: (id: string) => ['users', 'detail', id] as const,
  },

  roles: {
    all: ['roles'] as const,
    list: (params: ListParams) => ['roles', 'list', params] as const,
    detail: (id: number) => ['roles', 'detail', id] as const,
  },

  apiTokens: {
    all: ['api-tokens'] as const,
    list: () => ['api-tokens', 'list'] as const,
  },

  webhooks: {
    all: ['webhooks'] as const,
    list: () => ['webhooks', 'list'] as const,
    detail: (id: number) => ['webhooks', 'detail', id] as const,
    logs: (id: number) => ['webhooks', 'logs', id] as const,
    events: () => ['webhooks', 'events'] as const,
  },

  audit: {
    list: (params: ListParams & { action?: string }) => ['audit', 'list', params] as const,
  },

  tenants: {
    all: ['tenants'] as const,
    children: (params: ListParams) => ['tenants', 'children', params] as const,
    detail: (id: string) => ['tenants', 'detail', id] as const,
  },

  assistant: {
    all: ['assistant'] as const,
    conversations: (params: ListParams & { search?: string }) =>
      ['assistant', 'conversations', params] as const,
    conversation: (id: string) => ['assistant', 'conversation', id] as const,
  },

  billing: {
    all: ['billing'] as const,
    plans: {
      all: ['billing', 'plans'] as const,
      list: () => ['billing', 'plans', 'list'] as const,
      detail: (id: string) => ['billing', 'plans', 'detail', id] as const,
      catalog: () => ['billing', 'plans', 'catalog'] as const,
    },
    subscription: {
      current: () => ['billing', 'subscription'] as const,
    },
    gateways: {
      list: () => ['billing', 'gateways'] as const,
    },
    invoices: {
      list: () => ['billing', 'invoices', 'list'] as const,
      detail: (id: string) => ['billing', 'invoices', 'detail', id] as const,
    },
  },

  clients: {
    all: ['clients'] as const,
    list: (params: ListParams) => ['clients', 'list', params] as const,
    detail: (id: string) => ['clients', 'detail', id] as const,
    users: (clientId: string, params: ListParams) =>
      ['clients', clientId, 'users', params] as const,
    order: (clientId: string) => ['clients', clientId, 'order'] as const,
    contract: (clientId: string) => ['clients', clientId, 'contract'] as const,
  },

  drivers: {
    all: ['drivers'] as const,
    list: (params: ListParams & { client_id?: string }) => ['drivers', 'list', params] as const,
    detail: (id: string) => ['drivers', 'detail', id] as const,
  },

  services: {
    all: ['services'] as const,
    list: (params: ListParams) => ['services', 'list', params] as const,
    detail: (id: string) => ['services', 'detail', id] as const,
  },

  contracts: {
    all: ['contracts'] as const,
    list: (params: ListParams) => ['contracts', 'list', params] as const,
    detail: (id: string) => ['contracts', 'detail', id] as const,
  },

  vehicles: {
    all: ['vehicles'] as const,
    list: (params: ListParams & { client_id?: string }) => ['vehicles', 'list', params] as const,
    detail: (id: string) => ['vehicles', 'detail', id] as const,
    history: (id: string) => ['vehicles', 'history', id] as const,
  },

  vehicleData: {
    all: ['vehicle-data'] as const,
    config: () => ['vehicle-data', 'config'] as const,
  },

  equipments: {
    all: ['equipments'] as const,
    list: (params: ListParams & { available?: boolean; vehicle_id?: string }) =>
      ['equipments', 'list', params] as const,
    detail: (id: string) => ['equipments', 'detail', id] as const,
    history: (id: string) => ['equipments', 'history', id] as const,
  },

  tracking: {
    all: ['tracking'] as const,
    live: (search?: string) => ['tracking', 'live', search ?? ''] as const,
    history: (vehicleId: string, from: string, to: string) =>
      ['tracking', 'history', vehicleId, from, to] as const,
    status: () => ['tracking', 'status'] as const,
    commands: (deviceId: string) => ['tracking', 'commands', deviceId] as const,
  },

  reports: {
    commands: (params: { from?: string; to?: string; client_id?: string; vehicle_id?: string }) =>
      ['reports', 'commands', params] as const,
    positions: (params: { from?: string; to?: string; client_id?: string; vehicle_id: string }) =>
      ['reports', 'positions', params] as const,
    stops: (params: { from?: string; to?: string; client_id?: string }) =>
      ['reports', 'stops', params] as const,
    trips: (params: { from?: string; to?: string; client_id?: string; vehicle_id: string }) =>
      ['reports', 'trips', params] as const,
    events: (params: { from?: string; to?: string; client_id?: string; vehicle_id?: string }) =>
      ['reports', 'events', params] as const,
  },

  geofences: {
    all: ['geofences'] as const,
    list: (params: ListParams & { client_id?: string; type?: string; is_active?: boolean }) =>
      ['geofences', 'list', params] as const,
    detail: (id: string) => ['geofences', 'detail', id] as const,
    map: (params?: { client_id?: string; is_active?: boolean }) =>
      ['geofences', 'map', params ?? {}] as const,
    events: (
      params: ListParams & {
        vehicle_id?: string
        geofence_id?: string
        client_id?: string
        type?: string
        from?: string
        to?: string
      },
    ) => ['geofences', 'events', params] as const,
  },

  pois: {
    all: ['pois'] as const,
    list: (params: ListParams & { client_id?: string; category_id?: string; is_active?: boolean }) =>
      ['pois', 'list', params] as const,
    detail: (id: string) => ['pois', 'detail', id] as const,
    map: (params?: { client_id?: string; category_id?: string; is_active?: boolean }) =>
      ['pois', 'map', params ?? {}] as const,
    categories: () => ['pois', 'categories'] as const,
  },

  alerts: {
    all: ['alerts'] as const,
    list: (
      params: ListParams & {
        vehicle_id?: string
        type?: string
        status?: string
        severity?: string
        from?: string
        to?: string
        sort?: string
      },
    ) => ['alerts', 'list', params] as const,
    detail: (id: string) => ['alerts', 'detail', id] as const,
    map: (status?: string) => ['alerts', 'map', status ?? 'open'] as const,
    dashboard: () => ['alerts', 'dashboard'] as const,
    configs: () => ['alerts', 'configs'] as const,
  },

  notifications: {
    all: ['notifications'] as const,
    list: (params: ListParams & { unread?: boolean }) => ['notifications', 'list', params] as const,
    unreadCount: () => ['notifications', 'unread-count'] as const,
  },

  serviceOrders: {
    all: ['service-orders'] as const,
    list: (params: ServiceOrderQueryParams) => ['service-orders', 'list', params] as const,
    detail: (id: string) => ['service-orders', 'detail', id] as const,
    kanban: (params?: ServiceOrderQueryParams) => ['service-orders', 'kanban', params ?? {}] as const,
    calendar: (params?: ServiceOrderQueryParams) =>
      ['service-orders', 'calendar', params ?? {}] as const,
    history: (id: string) => ['service-orders', 'history', id] as const,
  },

  finance: {
    all: ['finance'] as const,
    plans: {
      all: ['finance', 'plans'] as const,
      list: (params: ListParams & { is_active?: boolean | string }) =>
        ['finance', 'plans', 'list', params] as const,
      detail: (id: string) => ['finance', 'plans', 'detail', id] as const,
    },
    subscriptions: {
      all: ['finance', 'subscriptions'] as const,
      list: (params: ListParams & { status?: string; client_id?: string }) =>
        ['finance', 'subscriptions', 'list', params] as const,
      detail: (id: string) => ['finance', 'subscriptions', 'detail', id] as const,
    },
    billings: {
      all: ['finance', 'billings'] as const,
      list: (
        params: ListParams & {
          status?: string
          client_id?: string
          subscription_id?: string
          due_from?: string
          due_to?: string
        },
      ) => ['finance', 'billings', 'list', params] as const,
      detail: (id: string) => ['finance', 'billings', 'detail', id] as const,
    },
    gatewayConfig: () => ['finance', 'gateway-config'] as const,
    dashboard: () => ['finance', 'dashboard'] as const,
    reports: (params: { type: string; from?: string; to?: string; status?: string }) =>
      ['finance', 'reports', params] as const,
    clientOverview: (clientId: string) => ['finance', 'client-overview', clientId] as const,
    portal: {
      subscription: () => ['finance', 'portal', 'subscription'] as const,
      billings: (
        params: ListParams & { status?: string; due_from?: string; due_to?: string },
      ) => ['finance', 'portal', 'billings', params] as const,
      billing: (id: string) => ['finance', 'portal', 'billing', id] as const,
    },
  },
} as const
