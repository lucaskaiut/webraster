import { Link } from 'react-router'
import type { LucideIcon } from 'lucide-react'
import { Card, CardContent } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'

export function MetricCard({
  label,
  value,
  hint,
  icon: Icon,
  tone = 'neutral',
  to,
}: {
  label: string
  value: number | string
  hint?: string
  icon: LucideIcon
  tone?: 'neutral' | 'success' | 'warning' | 'danger'
  to?: string
}) {
  const card = (
    <Card className={cn('h-full', to && 'transition-shadow hover:shadow-raised')}>
      <CardContent className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-[13px] text-muted">{label}</p>
          <p className="mt-1 text-2xl font-semibold text-foreground">{value}</p>
          {hint && <p className="mt-0.5 text-[12px] text-subtle">{hint}</p>}
        </div>
        <span
          className={cn(
            'flex size-9 shrink-0 items-center justify-center rounded-lg',
            tone === 'neutral' && 'bg-surface-2 text-muted',
            tone === 'success' && 'bg-success-soft text-success',
            tone === 'warning' && 'bg-warning-soft text-warning',
            tone === 'danger' && 'bg-danger-soft text-danger',
          )}
        >
          <Icon className="size-4.5" aria-hidden="true" />
        </span>
      </CardContent>
    </Card>
  )

  if (!to) return card

  return (
    <Link to={to} className="block h-full rounded-xl">
      {card}
    </Link>
  )
}
