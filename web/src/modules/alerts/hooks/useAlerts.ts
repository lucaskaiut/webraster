import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
  type QueryKey,
} from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type { PaginatedResponse } from '@/shared/types/api'
import type { Alert, AlertDashboardStats, AlertStatus } from '@/shared/types/models'
import { alertsService, type AlertConfigPayload, type AlertListParams } from '../services/alerts.service'

/** Intervalo de polling dos alertas (dashboard e mapa). */
export const ALERTS_POLL_INTERVAL_MS = 15_000

type ManualAlertStatus = Extract<AlertStatus, 'acknowledged' | 'resolved'>

function withOptimisticStatus(alert: Alert, id: string, status: ManualAlertStatus): Alert {
  if (alert.id !== id) {
    return alert
  }

  const now = new Date().toISOString()

  return {
    ...alert,
    status,
    acknowledged_at: alert.acknowledged_at ?? now,
    resolved_at: status === 'resolved' ? (alert.resolved_at ?? now) : alert.resolved_at,
  }
}

function applyOptimisticAlertStatus(
  queryClient: QueryClient,
  id: string,
  status: ManualAlertStatus,
): void {
  queryClient.setQueriesData<PaginatedResponse<Alert>>({ queryKey: ['alerts', 'list'] }, (current) => {
    if (!current || !Array.isArray(current.data)) {
      return current
    }

    const data = current.data.map((alert) => withOptimisticStatus(alert, id, status))

    return data.some((alert, index) => alert !== current.data[index]) ? { ...current, data } : current
  })

  // O mapa só exibe alertas abertos; reconhecer/resolver remove o pin.
  queryClient.setQueriesData<Alert[]>({ queryKey: ['alerts', 'map'] }, (current) => {
    if (!Array.isArray(current)) {
      return current
    }

    return current.filter((alert) => alert.id !== id)
  })

  queryClient.setQueryData<Alert>(queryKeys.alerts.detail(id), (current) =>
    current ? withOptimisticStatus(current, id, status) : current,
  )

  queryClient.setQueryData<AlertDashboardStats>(queryKeys.alerts.dashboard(), (current) => {
    if (!current) {
      return current
    }

    const target = current.critical_open.find((alert) => alert.id === id)

    if (!target) {
      return current
    }

    const bySeverity = { ...current.by_severity }
    bySeverity[target.severity] = Math.max(0, (bySeverity[target.severity] ?? 1) - 1)

    return {
      ...current,
      open_total: Math.max(0, current.open_total - 1),
      critical_open_total: Math.max(0, current.critical_open_total - 1),
      by_severity: bySeverity,
      critical_open: current.critical_open.filter((alert) => alert.id !== id),
    }
  })
}

function snapshotAlertCaches(queryClient: QueryClient): Array<[QueryKey, unknown]> {
  return queryClient.getQueriesData({ queryKey: queryKeys.alerts.all })
}

function restoreAlertCaches(
  queryClient: QueryClient,
  previous: Array<[QueryKey, unknown]> | undefined,
): void {
  previous?.forEach(([key, data]) => queryClient.setQueryData(key, data))
}

export function useAlertsQuery(params: AlertListParams, options?: { enabled?: boolean; refetchInterval?: number }) {
  return useQuery({
    queryKey: queryKeys.alerts.list(params),
    queryFn: () => alertsService.list(params),
    placeholderData: keepPreviousData,
    enabled: options?.enabled ?? true,
    refetchInterval: options?.refetchInterval,
  })
}

export function useAlertsMapQuery(status = 'open', enabled = true) {
  return useQuery({
    queryKey: queryKeys.alerts.map(status),
    queryFn: () => alertsService.map(status),
    enabled,
    refetchInterval: ALERTS_POLL_INTERVAL_MS,
  })
}

export function useAlertDashboardQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.alerts.dashboard(),
    queryFn: () => alertsService.dashboard(),
    enabled,
    refetchInterval: ALERTS_POLL_INTERVAL_MS,
  })
}

export function useAlertConfigsQuery() {
  return useQuery({
    queryKey: queryKeys.alerts.configs(),
    queryFn: () => alertsService.configs(),
  })
}

export function useAcknowledgeAlert() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => alertsService.acknowledge(id),
    onMutate: (id) => {
      const previous = snapshotAlertCaches(queryClient)
      applyOptimisticAlertStatus(queryClient, id, 'acknowledged')

      return { previous }
    },
    onError: (_error, _id, context) => {
      restoreAlertCaches(queryClient, context?.previous)
      toast.error('Não foi possível reconhecer o alerta')
    },
    onSuccess: () => {
      toast.success('Alerta reconhecido')
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.all })
    },
  })
}

export function useResolveAlert() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => alertsService.resolve(id),
    onMutate: (id) => {
      const previous = snapshotAlertCaches(queryClient)
      applyOptimisticAlertStatus(queryClient, id, 'resolved')

      return { previous }
    },
    onError: (_error, _id, context) => {
      restoreAlertCaches(queryClient, context?.previous)
      toast.error('Não foi possível resolver o alerta')
    },
    onSuccess: () => {
      toast.success('Alerta resolvido')
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.all })
    },
  })
}

export function useCreateAlertConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: AlertConfigPayload) => alertsService.createConfig(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.configs() })
      toast.success('Alerta criado')
    },
  })
}

export function useUpdateAlertConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: Partial<AlertConfigPayload> }) =>
      alertsService.updateConfig(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.configs() })
      toast.success('Configuração salva')
    },
  })
}

export function useDeleteAlertConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => alertsService.deleteConfig(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.configs() })
      toast.success('Configuração removida')
    },
  })
}

export function usePortalAlertConfigsQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.alerts.portalConfigs(),
    queryFn: () => alertsService.portalConfigs(),
    enabled,
  })
}

export function useUpdatePortalAlertConfig() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ type, isEnabled }: { type: string; isEnabled: boolean }) =>
      alertsService.updatePortalConfig(type, isEnabled),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.portalConfigs() })
    },
  })
}

export function useClientAlertConfigsQuery(clientId: string | undefined) {
  return useQuery({
    queryKey: queryKeys.alerts.clientConfigs(clientId ?? ''),
    queryFn: () => alertsService.clientConfigs(clientId as string),
    enabled: Boolean(clientId),
  })
}
