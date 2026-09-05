import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  equipmentsService,
  type EquipmentListParams,
  type EquipmentPayload,
} from '../services/equipments.service'

export function useEquipmentsQuery(params: EquipmentListParams) {
  return useQuery({
    queryKey: queryKeys.equipments.list(params),
    queryFn: () => equipmentsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useEquipmentQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.equipments.detail(id ?? ''),
    queryFn: () => equipmentsService.get(id!),
    enabled: !!id,
  })
}

export function useCreateEquipment() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: EquipmentPayload) => equipmentsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
      toast.success('Equipamento criado', 'O equipamento foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateEquipment(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: EquipmentPayload) => equipmentsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
      toast.success('Equipamento atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteEquipment() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => equipmentsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
      toast.success('Equipamento removido', 'O equipamento foi excluído com sucesso.')
    },
  })
}
