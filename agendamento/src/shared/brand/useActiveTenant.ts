import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'

/**
 * Tenant atualmente em contexto: para masters, o selecionado no seletor;
 * para os demais, o próprio tenant da sessão.
 */
export function useActiveTenant() {
  const tenant = useSessionStore((state) => state.tenant)
  const isMaster = useSessionStore((state) => state.isMaster)
  const availableTenants = useSessionStore((state) => state.availableTenants)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)

  if (!isMaster) {
    return tenant
  }

  return availableTenants.find((item) => item.id === selectedTenantId) ?? tenant
}
