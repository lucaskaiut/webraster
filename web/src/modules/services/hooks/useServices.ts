import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  servicesService,
  type ServiceListParams,
  type ServicePayload,
} from '../services/services.service'

export function useServicesQuery(params: ServiceListParams) {
  return useQuery({
    queryKey: queryKeys.services.list(params),
    queryFn: () => servicesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useServiceQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.services.detail(id ?? ''),
    queryFn: () => servicesService.get(id!),
    enabled: !!id,
  })
}

export function useCreateService() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ServicePayload) => servicesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.services.all })
      toast.success('Serviço criado', 'O serviço foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateService(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ServicePayload) => servicesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.services.all })
      toast.success('Serviço atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteService() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => servicesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.services.all })
      toast.success('Serviço removido', 'O serviço foi excluído com sucesso.')
    },
  })
}
