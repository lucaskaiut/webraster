import { createContext, useContext, type ReactNode } from 'react'
import { NavLink } from 'react-router'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Tooltip } from './Tooltip'

const SidebarContext = createContext(false)

export function Sidebar({
  header,
  footer,
  children,
  className,
  collapsed = false,
}: {
  header?: ReactNode
  footer?: ReactNode
  children: ReactNode
  className?: string
  collapsed?: boolean
}) {
  return (
    <SidebarContext.Provider value={collapsed}>
      <aside
        className={cn(
          'flex h-full flex-col overflow-hidden bg-surface transition-[width] duration-200 ease-in-out motion-reduce:transition-none',
          collapsed ? 'w-16' : 'w-64',
          className,
        )}
      >
        {header && <div className={cn('pt-5 pb-2', collapsed ? 'px-2' : 'px-4')}>{header}</div>}
        <nav
          className={cn('flex-1 space-y-6 overflow-y-auto py-4', collapsed ? 'px-2' : 'px-3')}
          aria-label="Menu principal"
        >
          {children}
        </nav>
        {footer && <div className={cn('pb-4', collapsed ? 'px-2' : 'px-3')}>{footer}</div>}
      </aside>
    </SidebarContext.Provider>
  )
}

export function SidebarGroup({ label, children }: { label?: string; children: ReactNode }) {
  const collapsed = useContext(SidebarContext)

  return (
    <div>
      {label &&
        (collapsed ? (
          <span className="sr-only">{label}</span>
        ) : (
          <p className="px-3 pb-2 text-[11px] font-semibold tracking-wider text-subtle uppercase">
            {label}
          </p>
        ))}
      <div className={collapsed ? 'flex flex-col items-center gap-0.5' : 'space-y-0.5'}>{children}</div>
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
  const collapsed = useContext(SidebarContext)

  const link = (
    <NavLink
      to={to}
      onClick={onNavigate}
      aria-label={collapsed ? label : undefined}
      className={({ isActive }) =>
        cn(
          'flex items-center rounded-lg text-sm transition-colors',
          collapsed ? 'size-9 justify-center' : 'h-9 gap-3 px-3',
          isActive
            ? 'bg-primary-soft font-medium text-primary'
            : 'text-muted hover:bg-surface-2 hover:text-foreground',
        )
      }
    >
      <Icon className="size-4.5 shrink-0" aria-hidden="true" />
      {!collapsed && <span className="truncate">{label}</span>}
    </NavLink>
  )

  if (!collapsed) {
    return link
  }

  return (
    <Tooltip
      content={label}
      side="right"
      maxWidth={220}
      className="px-2.5 py-1.5 text-xs font-medium"
    >
      {link}
    </Tooltip>
  )
}
