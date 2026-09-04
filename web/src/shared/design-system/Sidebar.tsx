import type { ReactNode } from 'react'
import { NavLink } from 'react-router'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

export function Sidebar({
  header,
  footer,
  children,
  className,
}: {
  header?: ReactNode
  footer?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <aside className={cn('flex h-full w-64 flex-col bg-surface', className)}>
      {header && <div className="px-4 pt-5 pb-2">{header}</div>}
      <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-4" aria-label="Menu principal">
        {children}
      </nav>
      {footer && <div className="px-3 pb-4">{footer}</div>}
    </aside>
  )
}

export function SidebarGroup({ label, children }: { label?: string; children: ReactNode }) {
  return (
    <div>
      {label && (
        <p className="px-3 pb-2 text-[11px] font-semibold tracking-wider text-subtle uppercase">
          {label}
        </p>
      )}
      <div className="space-y-0.5">{children}</div>
    </div>
  )
}

export function SidebarItem({
  to,
  icon: Icon,
  label,
  onNavigate,
}: {
  to: string
  icon: LucideIcon
  label: string
  onNavigate?: () => void
}) {
  return (
    <NavLink
      to={to}
      onClick={onNavigate}
      className={({ isActive }) =>
        cn(
          'flex h-9 items-center gap-3 rounded-lg px-3 text-sm transition-colors',
          isActive
            ? 'bg-primary-soft font-medium text-primary'
            : 'text-muted hover:bg-surface-2 hover:text-foreground',
        )
      }
    >
      <Icon className="size-4.5 shrink-0" aria-hidden="true" />
      <span className="truncate">{label}</span>
    </NavLink>
  )
}
