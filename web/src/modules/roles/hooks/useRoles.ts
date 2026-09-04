import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { rolesService, type RolePayload } from '../services/roles.service'

export function useRolesQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.roles.list(params),
    queryFn: () => rolesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useRoleQuery(id: number | undefined) {
  return useQuery({
    queryKey: queryKeys.roles.detail(id ?? 0),
    queryFn: () => rolesService.get(id!),
    enabled: !!id,
  })
}

export function useCreateRole() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: RolePayload) => rolesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all })
      toast.success('Perfil criado', 'O perfil de acesso foi criado com sucesso.')
    },
  })
}

export function useUpdateRole(id: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: RolePayload) => rolesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all })
      toast.success('Perfil atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteRole() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) => rolesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.roles.all })
      toast.success('Perfil removido', 'O perfil foi excluído com sucesso.')
    },
  })
}
