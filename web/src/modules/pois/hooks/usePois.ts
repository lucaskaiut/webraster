import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { poisService, type PoiListParams, type PoiPayload } from '../services/pois.service'

export function usePoisQuery(params: PoiListParams) {
  return useQuery({
    queryKey: queryKeys.pois.list(params),
    queryFn: () => poisService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function usePoiQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.pois.detail(id ?? ''),
    queryFn: () => poisService.get(id!),
    enabled: !!id,
  })
}

export function usePoisMapQuery(
  params?: { client_id?: string; category_id?: string; is_active?: boolean },
  enabled = true,
) {
  return useQuery({
    queryKey: queryKeys.pois.map(params),
    queryFn: () => poisService.map(params),
    enabled,
  })
}

export function usePoiCategoriesQuery() {
  return useQuery({
    queryKey: queryKeys.pois.categories(),
    queryFn: () => poisService.categories(),
  })
}

export function useCreatePoi() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PoiPayload) => poisService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.pois.all })
      toast.success('POI criado', 'O ponto de interesse foi cadastrado.')
    },
  })
}

export function useUpdatePoi(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: Partial<PoiPayload>) => poisService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.pois.all })
      toast.success('POI atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeletePoi() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => poisService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.pois.all })
      toast.success('POI removido', 'O ponto de interesse foi excluído.')
    },
  })
}
