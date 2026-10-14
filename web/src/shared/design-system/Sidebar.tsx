import {
  Children,
  createContext,
  isValidElement,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { NavLink, useLocation } from 'react-router'
import { ChevronDown, type LucideIcon } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Tooltip } from './Tooltip'

type SidebarContextValue = {
  collapsed: boolean
  /** Item aninhado em um grupo: recebe indentação para reforçar a hierarquia. */
  nested: boolean
}

const SidebarContext = createContext<SidebarContextValue>({ collapsed: false, nested: false })

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
    <SidebarContext.Provider value={{ collapsed, nested: false }}>
      <aside
        className={cn(
          'flex h-full flex-col overflow-hidden bg-surface transition-[width] duration-200 ease-in-out motion-reduce:transition-none',
          collapsed ? 'w-16' : 'w-64',
          className,
        )}
      >
        {header && <div className={cn('pt-5 pb-2', collapsed ? 'px-2' : 'px-4')}>{header}</div>}
        <nav
          className={cn('flex-1 space-y-2 overflow-y-auto py-4', collapsed ? 'px-2' : 'px-3')}
          aria-label="Menu principal"
        >
          {children}
        </nav>
        {footer && <div className={cn('pb-4', collapsed ? 'px-2' : 'px-3')}>{footer}</div>}
      </aside>
    </SidebarContext.Provider>
  )
}

function collectPaths(children: ReactNode): string[] {
  const paths: string[] = []

  Children.forEach(children, (child) => {
    if (!isValidElement(child)) return

    const props = child.props as { to?: string; children?: ReactNode }

    if (typeof props.to === 'string') {
      paths.push(props.to)
    }

    if (props.children) {
      paths.push(...collectPaths(props.children))
    }
  })

  return paths
}

function matchesPath(currentPath: string, target: string): boolean {
  if (target === '/') return currentPath === '/'

  return currentPath === target || currentPath.startsWith(`${target}/`)
}

/**
 * Grupo de itens do menu. Com `collapsible`, o título funciona como um
 * accordion: as opções aparecem abaixo ao clicar e o grupo abre
 * automaticamente quando uma rota filha está ativa.
 *
 * Os itens filhos são indentados automaticamente para reforçar a
 * hierarquia entre o menu e o submenu.
 */
export function SidebarGroup({
  label,
  icon: Icon,
  children,
  collapsible = false,
  defaultOpen = false,
}: {
  label?: string
  icon?: LucideIcon
  children: ReactNode
  collapsible?: boolean
  defaultOpen?: boolean
}) {
  const { collapsed } = useContext(SidebarContext)
  const location = useLocation()
  const paths = useMemo(() => collectPaths(children), [children])
  const hasActive = paths.some((path) => matchesPath(location.pathname, path))
  const [open, setOpen] = useState(defaultOpen || hasActive)

  useEffect(() => {
    if (hasActive) {
      setOpen(true)
    }
  }, [hasActive])

  if (collapsed) {
    return (
      <div>
        {label && <span className="sr-only">{label}</span>}
        <div className="flex flex-col items-center gap-0.5">{children}</div>
      </div>
    )
  }

  if (!label) {
    return <div className="space-y-0.5">{children}</div>
  }

  if (!collapsible) {
    return (
      <div>
        <div className="flex items-center gap-3 px-3 pb-2">
          {Icon && <Icon className="size-4 shrink-0 text-subtle" aria-hidden="true" />}
          <p className="text-[11px] font-semibold tracking-wider text-subtle uppercase">{label}</p>
        </div>
        <SidebarContext.Provider value={{ collapsed, nested: true }}>
          <div className="space-y-0.5">{children}</div>
        </SidebarContext.Provider>
      </div>
    )
  }

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        className={cn(
          'flex h-9 w-full cursor-pointer items-center gap-3 rounded-lg px-3 text-sm font-semibold tracking-wide uppercase transition-colors',
          hasActive ? 'text-foreground' : 'text-subtle hover:bg-surface-2 hover:text-foreground',
        )}
      >
        {Icon && <Icon className="size-4 shrink-0" aria-hidden="true" />}
        <span className="flex-1 text-left text-[11px]">{label}</span>
        <ChevronDown
          className={cn(
            'size-3.5 shrink-0 transition-transform duration-200',
            !open && '-rotate-90',
          )}
          aria-hidden="true"
        />
      </button>
      {open && (
        <SidebarContext.Provider value={{ collapsed, nested: true }}>
          <div className="mt-0.5 space-y-0.5">{children}</div>
        </SidebarContext.Provider>
      )}
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
  const { collapsed, nested } = useContext(SidebarContext)

  const link = (
    <NavLink
      to={to}
      onClick={onNavigate}
      aria-label={collapsed ? label : undefined}
      className={({ isActive }) =>
        cn(
          'flex items-center rounded-lg text-sm transition-colors',
          collapsed
            ? 'size-9 justify-center'
            : cn('h-9 gap-3 pr-3', nested ? 'pl-8' : 'pl-3'),
          isActive
            ? 'bg-primary-soft font-medium text-primary'
            : 'text-muted hover:bg-surface-2 hover:text-foreground',
        )
      }
    >
      <Icon className={cn('shrink-0', nested ? 'size-4' : 'size-4.5')} aria-hidden="true" />
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
