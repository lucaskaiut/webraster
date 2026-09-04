import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { tenantsService, type CreateChildTenantPayload } from '../services/tenants.service'

export function useTenantChildrenQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.tenants.children(params),
    queryFn: () => tenantsService.listChildren(params),
    placeholderData: keepPreviousData,
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
