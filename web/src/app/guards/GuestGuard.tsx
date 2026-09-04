import { Navigate, Outlet } from 'react-router'
import { useSessionStore } from '@/shared/stores/session.store'
import { FullScreenLoading } from '@/shared/design-system'

/**
 * Permite acesso apenas a visitantes (não autenticados).
 */
export function GuestGuard() {
  const status = useSessionStore((state) => state.status)

  if (status === 'loading') return <FullScreenLoading />

  if (status === 'authenticated') return <Navigate to="/dashboard" replace />

  return <Outlet />
}
