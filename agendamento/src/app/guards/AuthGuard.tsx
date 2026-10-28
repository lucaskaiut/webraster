import { Navigate, Outlet, useLocation } from 'react-router'
import { useSessionStore } from '@/shared/stores/session.store'
import { FullScreenLoading } from '@/shared/design-system'

/**
 * Permite acesso apenas a usuários autenticados.
 */
export function AuthGuard() {
  const status = useSessionStore((state) => state.status)
  const location = useLocation()

  if (status === 'loading') return <FullScreenLoading />

  if (status === 'guest') {
    return <Navigate to="/auth/login" replace state={{ from: location.pathname }} />
  }

  return <Outlet />
}
