import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import { apiTokensService, type ApiTokenPayload } from '../services/api-tokens.service'

export function useApiTokensQuery() {
  return useQuery({
    queryKey: queryKeys.apiTokens.list(),
    queryFn: apiTokensService.list,
  })
}

export function useCreateApiToken() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ApiTokenPayload) => apiTokensService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.apiTokens.all })
    },
  })
}

export function useRevokeApiToken() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => apiTokensService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.apiTokens.all })
      toast.success('Token revogado', 'O token foi revogado e não pode mais ser utilizado.')
    },
  })
}

export function isTokenExpired(expiresAt: string | null): boolean {
  return expiresAt !== null && new Date(expiresAt).getTime() < Date.now()
}
