import { useState } from 'react'
import { Link } from 'react-router'
import { Bell } from 'lucide-react'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotificationsQuery,
  useUnreadNotificationsCount,
} from '../hooks/useNotifications'
import { cn } from '@/shared/utils/cn'

export function NotificationBell() {
  const { can } = usePermissions()
  const enabled = can(Permission.NOTIFICATION_READ) || can(Permission.ALERT_READ)
  const [open, setOpen] = useState(false)
  const countQuery = useUnreadNotificationsCount(enabled)
  const listQuery = useNotificationsQuery({ per_page: 8 })
  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()

  if (!enabled) return null

  const count = countQuery.data ?? 0

  return (
    <div className="relative">
      <button
        type="button"
        className="relative flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
        aria-label="Notificações"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <Bell className="size-4" />
        {count > 0 && (
          <span className="absolute top-1.5 right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {open && (
        <>
          <button
            type="button"
            className="fixed inset-0 z-40 cursor-default"
            aria-label="Fechar"
            onClick={() => setOpen(false)}
          />
          <div
            role="menu"
            className="animate-fade-in absolute top-full right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl bg-surface shadow-pop"
          >
            <div className="flex items-center justify-between px-3 py-2.5">
              <p className="text-sm font-semibold text-foreground">Notificações</p>
              {count > 0 && (
                <button
                  type="button"
                  className="text-xs font-medium text-primary"
                  onClick={() => markAll.mutate()}
                >
                  Marcar todas
                </button>
              )}
            </div>
            <div className="h-px bg-surface-2" />
            <div className="max-h-80 overflow-y-auto p-1.5">
              {(listQuery.data?.data.length ?? 0) === 0 ? (
                <p className="px-3 py-6 text-center text-sm text-muted">Nenhuma notificação</p>
              ) : (
                listQuery.data!.data.map((item) => (
                  <button
                    key={item.id}
                    type="button"
                    role="menuitem"
                    className={cn(
                      'block w-full rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-surface-2',
                      !item.read_at && 'bg-primary-soft/30',
                    )}
                    onClick={() => {
                      if (!item.read_at) markRead.mutate(item.id)
                      setOpen(false)
                    }}
                  >
                    <p className="text-sm font-medium text-foreground">{item.title}</p>
                    <p className="text-[12px] text-muted">
                      {(item.data?.plate as string) ?? item.type} ·{' '}
                      {item.created_at ? new Date(item.created_at).toLocaleString('pt-BR') : ''}
                    </p>
                  </button>
                ))
              )}
            </div>
            <div className="h-px bg-surface-2" />
            <div className="px-3 py-2.5">
              <Link
                to="/alerts"
                className="text-xs font-medium text-primary"
                onClick={() => setOpen(false)}
              >
                Ver todos os alertas
              </Link>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
