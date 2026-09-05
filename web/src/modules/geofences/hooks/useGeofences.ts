import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  geofenceEventsService,
  geofencesService,
  type GeofenceEventListParams,
  type GeofenceListParams,
  type GeofencePayload,
} from '../services/geofences.service'

export function useGeofencesQuery(params: GeofenceListParams) {
  return useQuery({
    queryKey: queryKeys.geofences.list(params),
    queryFn: () => geofencesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useGeofenceQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.geofences.detail(id ?? ''),
    queryFn: () => geofencesService.get(id!),
    enabled: !!id,
  })
}

export function useGeofencesMapQuery(params?: { client_id?: string; is_active?: boolean }, enabled = true) {
  return useQuery({
    queryKey: queryKeys.geofences.map(params),
    queryFn: () => geofencesService.map(params),
    enabled,
  })
}

export function useGeofenceEventsQuery(params: GeofenceEventListParams) {
  return useQuery({
    queryKey: queryKeys.geofences.events(params),
    queryFn: () => geofenceEventsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useCreateGeofence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: GeofencePayload) => geofencesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.geofences.all })
      toast.success('Geocerca criada', 'A geocerca foi cadastrada com sucesso.')
    },
  })
}

export function useUpdateGeofence(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: Partial<GeofencePayload>) => geofencesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.geofences.all })
      toast.success('Geocerca atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteGeofence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => geofencesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.geofences.all })
      toast.success('Geocerca removida', 'A geocerca foi excluída com sucesso.')
    },
  })
}
