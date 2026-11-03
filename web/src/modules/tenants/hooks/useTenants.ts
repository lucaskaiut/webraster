import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { applyTheme, type TenantTheme } from '@/shared/theme/theme'
import {
  tenantsService,
  type CreateChildTenantPayload,
  type UpdateChildTenantPayload,
  type UpdateTenantPayload,
} from '../services/tenants.service'

export function useTenantQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.tenants.current(),
    queryFn: () => tenantsService.getCurrent(),
    enabled,
  })
}

export function useTenantThemeQuery(enabled = true) {
  return useQuery({
    queryKey: queryKeys.tenants.theme(),
    queryFn: () => tenantsService.getTheme(),
    enabled,
  })
}

export function useUpdateTenantTheme() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: TenantTheme) => tenantsService.updateTheme(payload),
    onSuccess: (theme) => {
      queryClient.setQueryData(queryKeys.tenants.theme(), theme)
      applyTheme(theme)
      // A sessão traz o tema do tenant ativo: recarregar aplica em todo o painel.
      queryClient.invalidateQueries({ queryKey: queryKeys.session })
      toast.success('Tema salvo', 'As cores e a fonte do painel foram atualizadas.')
    },
  })
}

export function useUpdateTenant() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: UpdateTenantPayload) => tenantsService.updateCurrent(payload),
    onSuccess: (tenant) => {
      queryClient.setQueryData(queryKeys.tenants.current(), tenant)
      queryClient.invalidateQueries({ queryKey: queryKeys.session })
      queryClient.invalidateQueries({ queryKey: queryKeys.tenants.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.alerts.vehicleDefaultsAll })
      toast.success('Configurações salvas', 'As alterações foram salvas.')
    },
  })
}

export function useTenantChildrenQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.tenants.children(params),
    queryFn: () => tenantsService.listChildren(params),
    placeholderData: keepPreviousData,
  })
}

export function useTenantOverviewQuery() {
  return useQuery({
    queryKey: queryKeys.tenants.overview(),
    queryFn: () => tenantsService.overview(),
  })
}

export function useTenantModulesQuery() {
  return useQuery({
    queryKey: queryKeys.tenants.modules(),
    queryFn: () => tenantsService.listModules(),
    staleTime: Infinity,
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
