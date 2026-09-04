import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  driversService,
  type DriverListParams,
  type DriverPayload,
} from '../services/drivers.service'

export function useDriversQuery(params: DriverListParams) {
  return useQuery({
    queryKey: queryKeys.drivers.list(params),
    queryFn: () => driversService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useDriverQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.drivers.detail(id ?? ''),
    queryFn: () => driversService.get(id!),
    enabled: !!id,
  })
}

export function useCreateDriver() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: DriverPayload) => driversService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.drivers.all })
      toast.success('Motorista criado', 'O motorista foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateDriver(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: DriverPayload) => driversService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.drivers.all })
      toast.success('Motorista atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteDriver() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => driversService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.drivers.all })
      toast.success('Motorista removido', 'O motorista foi excluído com sucesso.')
    },
  })
}
