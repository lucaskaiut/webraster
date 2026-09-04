import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { usersService, type UserPayload } from '../services/users.service'

export function useUsersQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.users.list(params),
    queryFn: () => usersService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useUserQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.users.detail(id ?? ''),
    queryFn: () => usersService.get(id!),
    enabled: !!id,
  })
}

export function useCreateUser() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: UserPayload) => usersService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all })
      toast.success('Usuário criado', 'O usuário foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateUser(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: UserPayload) => usersService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all })
      toast.success('Usuário atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteUser() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => usersService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.users.all })
      toast.success('Usuário removido', 'O usuário foi excluído com sucesso.')
    },
  })
}
