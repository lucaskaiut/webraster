import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'

/**
 * Tenant atual é umbrella (parent_id nulo / raiz).
 * Para masters, usa o tenant selecionado no seletor.
 */
export function useIsUmbrellaTenant(): boolean {
  const tenant = useSessionStore((state) => state.tenant)
  const isMaster = useSessionStore((state) => state.isMaster)
  const availableTenants = useSessionStore((state) => state.availableTenants)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)

  if (isMaster && selectedTenantId) {
    const selected = availableTenants.find((item) => item.id === selectedTenantId)

    if (selected) {
      return Boolean(selected.is_umbrella)
    }
  }

  return Boolean(tenant?.is_umbrella)
}
