import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiErrorMessage, isApiError } from '@/shared/api/errors'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  stocksService,
  type StockListParams,
  type StockPayload,
} from '../services/stocks.service'

export function useStocksQuery(
  params: StockListParams,
  options?: { enabled?: boolean },
) {
  return useQuery({
    queryKey: queryKeys.stocks.list(params),
    queryFn: () => stocksService.list(params),
    placeholderData: keepPreviousData,
    enabled: options?.enabled ?? true,
  })
}

export function useStockQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.stocks.detail(id ?? ''),
    queryFn: () => stocksService.get(id!),
    enabled: !!id,
  })
}

export function useCreateStock() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: StockPayload) => stocksService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all })
      toast.success('Estoque criado', 'O estoque foi cadastrado com sucesso.')
    },
    onError: (error) => {
      toast.error(
        'Não foi possível criar o estoque',
        isApiError(error) ? apiErrorMessage(error) : 'Tente novamente.',
      )
    },
  })
}

export function useUpdateStock(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: StockPayload) => stocksService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all })
      toast.success('Estoque atualizado', 'As alterações foram salvas.')
    },
    onError: (error) => {
      toast.error(
        'Não foi possível salvar o estoque',
        isApiError(error) ? apiErrorMessage(error) : 'Tente novamente.',
      )
    },
  })
}

export function useDeleteStock() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => stocksService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.stocks.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.equipments.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all })
      toast.success('Estoque removido', 'O estoque foi excluído com sucesso.')
    },
    onError: (error) => {
      toast.error(
        'Não foi possível excluir o estoque',
        isApiError(error) ? apiErrorMessage(error) : 'Tente novamente.',
      )
    },
  })
}
