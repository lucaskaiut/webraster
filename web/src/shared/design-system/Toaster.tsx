import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react'
import { useToastStore, type ToastType } from '@/shared/stores/toast.store'
import { cn } from '@/shared/utils/cn'

const ICONS: Record<ToastType, { icon: typeof Info; className: string }> = {
  success: { icon: CheckCircle2, className: 'text-success' },
  error: { icon: AlertCircle, className: 'text-danger' },
  warning: { icon: AlertTriangle, className: 'text-warning' },
  info: { icon: Info, className: 'text-primary' },
}

export function Toaster() {
  const toasts = useToastStore((state) => state.toasts)
  const dismiss = useToastStore((state) => state.dismiss)

  return (
    <div
      aria-live="polite"
      aria-label="Notificações"
      className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-full max-w-sm flex-col gap-2"
    >
      {toasts.map((item) => {
        const { icon: Icon, className } = ICONS[item.type]

        return (
          <div
            key={item.id}
            role={item.type === 'error' ? 'alert' : 'status'}
            className="animate-toast-in pointer-events-auto flex items-start gap-3 rounded-xl bg-surface p-4 shadow-pop"
          >
            <Icon className={cn('mt-0.5 size-4.5 shrink-0', className)} aria-hidden="true" />
            <div className="min-w-0 flex-1 text-sm">
              <p className="font-semibold text-foreground">{item.title}</p>
              {item.description && <p className="mt-0.5 text-muted">{item.description}</p>}
            </div>
            <button
              type="button"
              onClick={() => dismiss(item.id)}
              aria-label="Fechar notificação"
              className="rounded-md p-1 text-subtle transition-colors hover:bg-surface-2 hover:text-foreground"
            >
              <X className="size-3.5" />
            </button>
          </div>
        )
      })}
    </div>
  )
}
