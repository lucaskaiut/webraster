import { useSessionStore } from '@/shared/stores/session.store'
import type { TenantModuleKey } from '@/shared/types/models'

/**
 * Módulos liberados para o tenant em contexto. A API já remove as
 * permissões de módulos desativados; use este hook só para telas que não
 * dependem de uma permissão do próprio módulo (ex.: abas do cliente).
 */
export function useTenantModules() {
  const modules = useSessionStore((state) => state.tenant?.modules)

  return {
    hasModule: (module: TenantModuleKey) => modules?.includes(module) ?? false,
  }
}
