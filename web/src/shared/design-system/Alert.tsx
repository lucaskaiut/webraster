import type { ReactNode } from 'react'
import { AlertCircle, AlertTriangle, CheckCircle2, Info } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

type AlertVariant = 'info' | 'success' | 'warning' | 'danger'

const STYLES: Record<AlertVariant, { container: string; icon: typeof Info }> = {
  info: { container: 'bg-primary-soft text-primary', icon: Info },
  success: { container: 'bg-success-soft text-success', icon: CheckCircle2 },
  warning: { container: 'bg-warning-soft text-warning', icon: AlertTriangle },
  danger: { container: 'bg-danger-soft text-danger', icon: AlertCircle },
}

export function Alert({
  variant = 'info',
  title,
  children,
  className,
}: {
  variant?: AlertVariant
  title: ReactNode
  children?: ReactNode
  className?: string
}) {
  const { container, icon: Icon } = STYLES[variant]

  return (
    <div role="alert" className={cn('flex gap-3 rounded-xl p-4', container, className)}>
      <Icon className="mt-0.5 size-4.5 shrink-0" aria-hidden="true" />
      <div className="min-w-0 text-sm">
        <p className="font-semibold">{title}</p>
        {children && <div className="mt-1 opacity-90">{children}</div>}
      </div>
    </div>
  )
}
