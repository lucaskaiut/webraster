import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  contractsService,
  type ContractListParams,
  type ContractPayload,
} from '../services/contracts.service'

export function useContractsQuery(params: ContractListParams) {
  return useQuery({
    queryKey: queryKeys.contracts.list(params),
    queryFn: () => contractsService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useContractQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.contracts.detail(id ?? ''),
    queryFn: () => contractsService.get(id!),
    enabled: !!id,
  })
}

export function useCreateContract() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ContractPayload) => contractsService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contracts.all })
      toast.success('Contrato criado', 'O contrato foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateContract(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ContractPayload) => contractsService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contracts.all })
      toast.success('Contrato atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteContract() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => contractsService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.contracts.all })
      toast.success('Contrato removido', 'O contrato foi excluído com sucesso.')
    },
  })
}
