import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { alertsService, type AlertConfigPayload, type AlertListParams } from '../services/alerts.service'

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
    refetchInterval: 15_000,
  })
}

export function useAlertDashboardQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.alerts.dashboard(),
    queryFn: () => alertsService.dashboard(),
    enabled,
    refetchInterval: 30_000,
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
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.all })
      toast.success('Alerta reconhecido')
    },
  })
}

export function useResolveAlert() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => alertsService.resolve(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.all })
      toast.success('Alerta resolvido')
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
