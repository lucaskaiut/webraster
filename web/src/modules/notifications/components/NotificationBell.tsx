import { useState } from 'react'
import { Link } from 'react-router'
import { Bell, Volume2, VolumeX } from 'lucide-react'
import { useQueryClient } from '@tanstack/react-query'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { Permission } from '@/shared/constants/permissions'
import { queryKeys } from '@/shared/constants/query-keys'
import { isAlertSoundMuted, setAlertSoundMuted } from '@/shared/utils/alert-sound'
import { cn } from '@/shared/utils/cn'
import { useResolveAlert } from '@/modules/alerts/hooks/useAlerts'
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotificationsQuery,
  useUnreadNotificationsCount,
} from '../hooks/useNotifications'
import { useNotificationAlertSound } from '../hooks/useNotificationAlertSound'

const LIST_PER_PAGE = 20

export function NotificationBell() {
  const { can } = usePermissions()
  const canManageAlerts = can(Permission.ALERT_MANAGE)
  const enabled = can(Permission.NOTIFICATION_READ) || can(Permission.ALERT_READ)
  const soundEnabled = enabled && can(Permission.ALERT_READ)
  const [open, setOpen] = useState(false)
  const [muted, setMuted] = useState(() => isAlertSoundMuted())
  const countQuery = useUnreadNotificationsCount(enabled)
  const listQuery = useNotificationsQuery({ per_page: LIST_PER_PAGE })
  const markRead = useMarkNotificationRead()
  const markAll = useMarkAllNotificationsRead()
  const resolveAlert = useResolveAlert()
  const queryClient = useQueryClient()

  useNotificationAlertSound(soundEnabled)

  if (!enabled) return null

  const count = countQuery.data ?? 0

  const toggleMuted = () => {
    const next = !muted
    setMuted(next)
    setAlertSoundMuted(next)
  }

  return (
    <div className="relative">
      <button
        type="button"
        className="relative flex size-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
        aria-label="Notificações"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <Bell className="size-4" />
        {count > 0 && (
          <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
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
              <div className="flex items-center gap-1">
                <button
                  type="button"
                  onClick={toggleMuted}
                  aria-label={muted ? 'Ativar sinal sonoro' : 'Silenciar sinal sonoro'}
                  title={muted ? 'Ativar sinal sonoro' : 'Silenciar sinal sonoro'}
                  className="flex size-7 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
                >
                  {muted ? <VolumeX className="size-4" /> : <Volume2 className="size-4" />}
                </button>
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
            </div>
            <div className="h-px bg-surface-2" />
            <div className="max-h-96 overflow-y-auto p-1.5">
              {(listQuery.data?.data.length ?? 0) === 0 ? (
                <p className="px-3 py-6 text-center text-sm text-muted">Nenhuma notificação</p>
              ) : (
                listQuery.data!.data.map((item) => (
                  <div
                    key={item.id}
                    className={cn(
                      'flex items-start gap-2 rounded-lg px-3 py-2.5 transition-colors hover:bg-surface-2',
                      !item.read_at && 'bg-primary-soft/30',
                    )}
                  >
                    <button
                      type="button"
                      role="menuitem"
                      className="flex min-w-0 flex-1 items-start gap-2 text-left"
                      onClick={() => {
                        if (!item.read_at) markRead.mutate(item.id)
                        setOpen(false)
                      }}
                    >
                      {!item.read_at && (
                        <span
                          aria-hidden="true"
                          className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                        />
                      )}
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium text-foreground">
                          {item.title}
                        </span>
                        <span className="block text-[12px] text-muted">
                          {(item.data?.plate as string) ?? item.alert?.vehicle?.plate ?? item.type} ·{' '}
                          {item.created_at ? new Date(item.created_at).toLocaleString('pt-BR') : ''}
                        </span>
                      </span>
                    </button>
                    {canManageAlerts && item.alert && item.alert.status !== 'resolved' && (
                      <button
                        type="button"
                        className="shrink-0 rounded-md px-2 py-1 text-[11px] font-medium text-primary transition-colors hover:bg-primary-soft"
                        disabled={resolveAlert.isPending}
                        onClick={() =>
                          resolveAlert.mutate(item.alert!.id, {
                            onSuccess: () =>
                              queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all }),
                          })
                        }
                      >
                        Encerrar
                      </button>
                    )}
                  </div>
                ))
              )}
            </div>
            <div className="h-px bg-surface-2" />
            <div className="flex items-center justify-between px-3 py-2.5">
              <Link
                to="/alerts"
                className="text-xs font-medium text-primary"
                onClick={() => setOpen(false)}
              >
                Ver todos os alertas
              </Link>
              <span className="text-[11px] text-subtle">
                {muted ? 'Som desativado' : 'Som ativado'}
              </span>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
