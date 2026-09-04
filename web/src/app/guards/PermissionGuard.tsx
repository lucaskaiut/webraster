import type { ReactNode } from 'react'
import { ShieldAlert } from 'lucide-react'
import type { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { ButtonLink, Card, EmptyState } from '@/shared/design-system'

interface PermissionGuardProps {
  permission?: Permission
  anyOf?: Permission[]
  /** Exige tenant umbrella (raiz / sem parent_id). */
  requiresUmbrella?: boolean
  /** Exige tenant filho — funcionalidades de uso final não estão no umbrella. */
  requiresChildTenant?: boolean
  fallback?: ReactNode
  children: ReactNode
}

/**
 * Renderiza o conteúdo apenas quando o usuário possui a permissão exigida.
 */
export function PermissionGuard({
  permission,
  anyOf,
  requiresUmbrella = false,
  requiresChildTenant = false,
  fallback,
  children,
}: PermissionGuardProps) {
  const { can, canAny } = usePermissions()
  const isUmbrella = useIsUmbrellaTenant()

  const allowed =
    (!requiresUmbrella || isUmbrella) &&
    (!requiresChildTenant || !isUmbrella) &&
    (permission === undefined || can(permission)) &&
    (anyOf === undefined || canAny(anyOf))

  if (!allowed) {
    if (fallback !== undefined) return fallback

    return (
      <Card>
        <EmptyState
          icon={ShieldAlert}
          title="Acesso restrito"
          description={
            requiresUmbrella && !isUmbrella
              ? 'O cadastro de planos está disponível apenas para o tenant raiz (umbrella).'
              : requiresChildTenant && isUmbrella
                ? 'Esta área é de uso operacional. Selecione uma empresa filha no seletor de tenant para continuar.'
                : 'Você não possui permissão para acessar esta área. Fale com o administrador da sua organização.'
          }
          action={
            <ButtonLink to="/dashboard" variant="secondary">
              Voltar ao dashboard
            </ButtonLink>
          }
        />
      </Card>
    )
  }

  return children
}

/**
 * Renderiza os filhos somente com permissão — sem fallback visual.
 * Útil para botões e itens de menu.
 */
export function Can({
  permission,
  anyOf,
  requiresUmbrella,
  requiresChildTenant,
  children,
}: {
  permission?: Permission
  anyOf?: Permission[]
  requiresUmbrella?: boolean
  requiresChildTenant?: boolean
  children: ReactNode
}) {
  return (
    <PermissionGuard
      permission={permission}
      anyOf={anyOf}
      requiresUmbrella={requiresUmbrella}
      requiresChildTenant={requiresChildTenant}
      fallback={null}
    >
      {children}
    </PermissionGuard>
  )
}
