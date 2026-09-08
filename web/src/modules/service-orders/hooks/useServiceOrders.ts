import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { isApiError } from '@/shared/api/errors'
import {
  serviceOrdersService,
  type ChangeStatusPayload,
  type ServiceOrderListParams,
  type ServiceOrderPayload,
} from '../services/service-orders.service'

export function useServiceOrdersQuery(params: ServiceOrderListParams) {
  return useQuery({
    queryKey: queryKeys.serviceOrders.list(params),
    queryFn: () => serviceOrdersService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useServiceOrderQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.serviceOrders.detail(id ?? ''),
    queryFn: () => serviceOrdersService.get(id!),
    enabled: !!id,
  })
}

export function useServiceOrdersKanbanQuery(params?: ServiceOrderListParams) {
  return useQuery({
    queryKey: queryKeys.serviceOrders.kanban(params),
    queryFn: () => serviceOrdersService.kanban(params),
  })
}

export function useServiceOrdersCalendarQuery(params?: ServiceOrderListParams) {
  return useQuery({
    queryKey: queryKeys.serviceOrders.calendar(params),
    queryFn: () => serviceOrdersService.calendar(params),
  })
}

function invalidateAll(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: queryKeys.serviceOrders.all })
}

export function useCreateServiceOrder() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: ServiceOrderPayload) => serviceOrdersService.create(payload),
    onSuccess: () => {
      invalidateAll(queryClient)
      toast.success('Ordem de serviço criada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useUpdateServiceOrder(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Partial<ServiceOrderPayload>) => serviceOrdersService.update(id, payload),
    onSuccess: () => {
      invalidateAll(queryClient)
      toast.success('Ordem de serviço atualizada')
    },
    onError: (error) => {
      if (isApiError(error)) toast.error(error.message)
    },
  })
}

export function useDeleteServiceOrder() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => serviceOrdersService.remove(id),
    onSuccess: () => {
      invalidateAll(queryClient)
      toast.success('Ordem de serviço removida')
    },
  })
}

export function useChangeServiceOrderStatus() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: ChangeStatusPayload }) =>
      serviceOrdersService.changeStatus(id, payload),
    onSuccess: () => {
      invalidateAll(queryClient)
      toast.success('Status atualizado')
    },
    onError: (error) => {
      if (isApiError(error)) {
        const msg = error.fieldErrors?.status?.[0] ?? error.fieldErrors?.cancellation_reason?.[0] ?? error.message
        toast.error(msg)
      }
    },
  })
}
