import { useEffect, useRef, type ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, ButtonLink } from '@/shared/design-system'
import { CLIENT_FINANCIAL_BLOCK_EVENT } from '@/shared/api/http'
import { queryKeys } from '@/shared/constants/query-keys'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import { useClientFinancialAccess } from '../hooks/useFinance'
import { BLOCKED_HOME, isAllowedWhileBlocked } from '../lib/financial-access'

/**
 * Cliente com cobrança em atraso além do prazo da régua de cobrança só acessa
 * o financeiro do portal: as demais telas redirecionam para as faturas.
 * A API aplica a mesma regra; aqui só ajustamos a navegação e o aviso.
 */
export function FinancialAccessGate({ children }: { children: ReactNode }) {
  const location = useLocation()
  const queryClient = useQueryClient()
  const access = useClientFinancialAccess()
  const { blocked } = access
  const wasBlocked = useRef(blocked)

  // A API recusou uma ação por inadimplência: atualiza situação e permissões.
  useEffect(() => {
    const onBlocked = () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.finance.portal.access() })
      void queryClient.invalidateQueries({ queryKey: queryKeys.session })
    }

    window.addEventListener(CLIENT_FINANCIAL_BLOCK_EVENT, onBlocked)
    return () => window.removeEventListener(CLIENT_FINANCIAL_BLOCK_EVENT, onBlocked)
  }, [queryClient])

  // Pagou: as permissões completas voltam com a sessão recarregada.
  useEffect(() => {
    if (wasBlocked.current && !blocked) {
      void queryClient.invalidateQueries({ queryKey: queryKeys.session })
    }
    wasBlocked.current = blocked
  }, [blocked, queryClient])

  if (!blocked || !access.data) return children

  if (!isAllowedWhileBlocked(location.pathname)) {
    return <Navigate to={BLOCKED_HOME} replace />
  }

  const { overdue_count, overdue_total_cents, oldest_due_at, billing_id, message } = access.data
  const billingPath = billing_id ? `/finance/portal/billings/${billing_id}` : null

  return (
    <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden">
      <Alert variant="warning" title="Acesso restrito ao financeiro" className="shrink-0">
        <p>{message}</p>
        <p className="mt-1">
          {overdue_count === 1 ? '1 cobrança em atraso' : `${overdue_count} cobranças em atraso`} ·{' '}
          {formatCurrency(overdue_total_cents / 100)}
          {oldest_due_at ? ` · vencida desde ${formatDate(oldest_due_at)}` : ''}
        </p>
        {billingPath && location.pathname !== billingPath && (
          <ButtonLink to={billingPath} size="sm" className="mt-3">
            Pagar agora
          </ButtonLink>
        )}
      </Alert>
      <div className="flex min-h-0 flex-1 flex-col overflow-hidden">{children}</div>
    </div>
  )
}
