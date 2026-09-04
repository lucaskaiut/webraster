import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Building2 } from 'lucide-react'
import { authService } from '@/modules/auth/services/auth.service'
import { Select } from '@/shared/design-system'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import { toast } from '@/shared/stores/toast.store'

/**
 * Seletor global de empresa — visível apenas para usuários master.
 * Inclui o tenant parent (home). Ao selecioná-lo, nenhum X-Tenant-Id é enviado.
 */
export function TenantSelector() {
  const isMaster = useSessionStore((state) => state.isMaster)
  const availableTenants = useSessionStore((state) => state.availableTenants)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)
  const setSelectedTenantId = useTenantContextStore((state) => state.setSelectedTenantId)
  const queryClient = useQueryClient()

  const switchTenant = useMutation({
    mutationFn: authService.selectTenant,
    onSuccess: async (tenant) => {
      setSelectedTenantId(tenant.id)
      await queryClient.invalidateQueries({
        predicate: (query) => query.queryKey[0] !== 'session',
      })
    },
    onError: () => {
      toast.error('Falha ao trocar empresa', 'Não foi possível selecionar o tenant informado.')
    },
  })

  if (!isMaster || availableTenants.length === 0) {
    return null
  }

  return (
    <div className="flex min-w-44 items-center gap-2">
      <Building2 className="size-4 shrink-0 text-muted" aria-hidden="true" />
      <Select
        aria-label="Selecionar empresa"
        className="h-9 min-w-40"
        disabled={switchTenant.isPending}
        value={selectedTenantId ?? ''}
        options={availableTenants.map((tenant) => ({
          value: tenant.id,
          label: tenant.is_home ? `${tenant.name} (grupo)` : tenant.name,
        }))}
        onChange={(event) => {
          const nextId = event.target.value
          if (!nextId || nextId === selectedTenantId) return
          switchTenant.mutate(nextId)
        }}
      />
    </div>
  )
}
