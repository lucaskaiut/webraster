import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { isApiError } from '@/shared/api/errors'
import {
  deviceCommandsService,
  type SendDeviceCommandPayload,
} from '../services/device-commands.service'

export function useDeviceCommandsQuery(deviceId: string | null | undefined, enabled = true) {
  return useQuery({
    queryKey: queryKeys.tracking.commands(deviceId ?? ''),
    queryFn: () => deviceCommandsService.list(deviceId!),
    enabled: !!deviceId && enabled,
    staleTime: 60_000,
  })
}

export function useDeviceCommandsHistoryQuery(deviceId: string | null | undefined, enabled = true) {
  return useQuery({
    queryKey: queryKeys.tracking.commandsHistory(deviceId ?? ''),
    queryFn: () => deviceCommandsService.history(deviceId!),
    enabled: !!deviceId && enabled,
  })
}

export function useSendDeviceCommand(deviceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SendDeviceCommandPayload) => deviceCommandsService.send(deviceId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tracking.commands(deviceId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.tracking.commandsHistory(deviceId) })
      toast.success('Comando enviado')
    },
    onError: (error) => {
      if (isApiError(error)) {
        const fieldError = error.fieldErrors?.type?.[0] ?? error.message
        toast.error(fieldError || 'Falha ao enviar comando')
        return
      }
      toast.error('Falha ao enviar comando')
    },
  })
}
