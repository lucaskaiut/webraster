import { useEffect, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { sessionQueryOptions } from '@/modules/auth/services/auth.service'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'

/**
 * Carrega a sessão atual (/auth/me) na inicialização e mantém o
 * estado global de sessão sincronizado com o cache do TanStack Query.
 * Para masters, garante um tenant filho válido selecionado.
 */
export function SessionProvider({ children }: { children: ReactNode }) {
  const { data, isSuccess, isError } = useQuery(sessionQueryOptions)
  const setSession = useSessionStore((state) => state.setSession)
  const setGuest = useSessionStore((state) => state.setGuest)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)
  const setSelectedTenantId = useTenantContextStore((state) => state.setSelectedTenantId)
  const clearSelectedTenantId = useTenantContextStore((state) => state.clearSelectedTenantId)

  useEffect(() => {
    if (isSuccess && data) setSession(data)
  }, [isSuccess, data, setSession])

  useEffect(() => {
    if (isError) {
      setGuest()
      clearSelectedTenantId()
    }
  }, [isError, setGuest, clearSelectedTenantId])

  useEffect(() => {
    if (!isSuccess || !data) return

    if (!data.is_master) {
      clearSelectedTenantId()
      return
    }

    const availableIds = new Set(data.available_tenants.map((tenant) => tenant.id))

    if (selectedTenantId && availableIds.has(selectedTenantId)) {
      return
    }

    // Preferência: parent/home (sem X-Tenant-Id); fallback no primeiro da lista.
    const home = data.available_tenants.find((tenant) => tenant.is_home)
    const fallback = home ?? data.available_tenants[0]

    if (fallback) {
      setSelectedTenantId(fallback.id)
    } else {
      clearSelectedTenantId()
    }
  }, [isSuccess, data, selectedTenantId, setSelectedTenantId, clearSelectedTenantId])

  return children
}
