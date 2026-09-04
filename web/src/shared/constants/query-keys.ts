import type { ListParams } from '@/shared/types/api'

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
  },

  drivers: {
    all: ['drivers'] as const,
    list: (params: ListParams & { client_id?: string }) => ['drivers', 'list', params] as const,
    detail: (id: string) => ['drivers', 'detail', id] as const,
  },
} as const
