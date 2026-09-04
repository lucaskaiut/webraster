import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import {
  tenantsService,
  type CreateChildTenantPayload,
  type UpdateChildTenantPayload,
} from '../services/tenants.service'

export function useTenantChildrenQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.tenants.children(params),
    queryFn: () => tenantsService.listChildren(params),
    placeholderData: keepPreviousData,
  })
}

export function useTenantChildQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.tenants.detail(id ?? ''),
    queryFn: () => tenantsService.getChild(id!),
    enabled: Boolean(id),
  })
}

export function useCreateChildTenant() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateChildTenantPayload) => tenantsService.createChild(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.all })
      toast.success('Empresa criada', 'A empresa e seu administrador foram cadastrados.')
    },
  })
}

export function useUpdateChildTenant(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: UpdateChildTenantPayload) => tenantsService.updateChild(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.detail(id) })
      toast.success('Empresa atualizada', 'Os dados e o acesso foram salvos.')
    },
  })
}
