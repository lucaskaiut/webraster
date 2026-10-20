import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  checklistsService,
  type ChecklistListParams,
  type ChecklistPayload,
} from '../services/checklists.service'

export function useChecklistsQuery(params: ChecklistListParams) {
  return useQuery({
    queryKey: queryKeys.checklists.list(params),
    queryFn: () => checklistsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useChecklistQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.checklists.detail(id ?? ''),
    queryFn: () => checklistsService.get(id!),
    enabled: !!id,
  })
}

export function useCreateChecklist() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ChecklistPayload) => checklistsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.checklists.all })
      toast.success('Checklist criado', 'O modelo foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateChecklist(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ChecklistPayload) => checklistsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.checklists.all })
      toast.success('Checklist atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteChecklist() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => checklistsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.checklists.all })
      toast.success('Checklist removido', 'O modelo foi removido com sucesso.')
    },
  })
}
