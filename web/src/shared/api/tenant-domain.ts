import { queryOptions } from '@tanstack/react-query'
import { http } from '@/shared/api/http'
import { isApiError } from '@/shared/api/errors'
import type { ApiResponse } from '@/shared/types/api'
import type { TenantTheme } from '@/shared/theme/theme'

/** Empresa dona do domínio em que o painel está aberto (dados públicos). */
export interface DomainTenant {
  id: string
  identifier: string
  name: string
  domain: string | null
  logo_url: string | null
  favicon_url: string | null
  theme?: TenantTheme | null
}

/**
 * Identifica a empresa pelo endereço do painel ({empresa}.nuvemax.srv.br).
 * Fora de um domínio de empresa (painel principal, localhost) devolve null.
 * A API aplica a mesma regra pelo header Origin: no domínio de uma empresa,
 * só usuários dela (ou masters do grupo) entram.
 */
export const domainTenantQueryOptions = queryOptions({
  queryKey: ['domain-tenant', window.location.hostname] as const,
  queryFn: async (): Promise<DomainTenant | null> => {
    try {
      const response = await http.get<ApiResponse<DomainTenant>>(
        `/public/domains/${encodeURIComponent(window.location.hostname)}`,
      )

      return response.data.data
    } catch (error) {
      if (isApiError(error) && error.status === 404) return null
      throw error
    }
  },
  retry: false,
  staleTime: Infinity,
})
