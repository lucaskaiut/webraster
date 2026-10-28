import { Suspense } from 'react'
import { Outlet } from 'react-router'
import { ClipboardList, Hammer, LogOut, Menu } from 'lucide-react'
import { AppLogo } from '@/shared/brand/AppLogo'
import { useActiveTenant } from '@/shared/brand/useActiveTenant'
import { TenantSelector } from '@/modules/auth/components/TenantSelector'
import { useSessionStore } from '@/shared/stores/session.store'
import { useUiStore } from '@/shared/stores/ui.store'
import { Permission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useLogout } from '@/modules/auth/hooks/useAuth'
import {
  Avatar,
  Container,
  Dropdown,
  DropdownItem,
  DropdownSeparator,
  Loading,
  Sidebar,
  SidebarGroup,
  SidebarItem,
  ThemeToggle,
  Topbar,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { TopbarSlotProvider, useTopbarSlotContent } from './topbar-slot'

function Brand({ collapsed = false }: { collapsed?: boolean }) {
  const activeTenant = useActiveTenant()

  if (collapsed) {
    return (
      <div className="flex justify-center">
        <AppLogo size="sm" className="h-5" />
      </div>
    )
  }

  return (
    <div className="px-1">
      <AppLogo size="sm" />
      {activeTenant?.name ? (
        <span className="mt-1 block truncate text-xs text-muted">{activeTenant.name}</span>
      ) : null}
    </div>
  )
}

function SidebarNavigation({
  onNavigate,
  collapsed = false,
}: {
  onNavigate?: () => void
  collapsed?: boolean
}) {
  const { can } = usePermissions()
  const isUmbrella = useIsUmbrellaTenant()
  /** Ordens de serviço só existem no tenant filho (empresa operacional). */
  const isOperatingTenant = !isUmbrella

  const showServiceOrders = isOperatingTenant && can(Permission.SERVICE_ORDER_READ)

  return (
    <Sidebar header={<Brand collapsed={collapsed} />} collapsed={collapsed}>
      {showServiceOrders && (
        <SidebarGroup label="Ordens de Serviço" icon={Hammer} collapsible defaultOpen>
          <SidebarItem
            to="/service-orders"
            icon={ClipboardList}
            label="Ordens de serviço"
            onNavigate={onNavigate}
          />
        </SidebarGroup>
      )}
    </Sidebar>
  )
}

function UserMenu() {
  const user = useSessionStore((state) => state.user)
  const logout = useLogout()

  if (!user) return null

  return (
    <Dropdown
      label="Menu do usuário"
      trigger={
        <span className="flex items-center gap-2.5 rounded-lg p-1.5 transition-colors hover:bg-surface-2">
          <Avatar name={user.name} size="sm" />
          <span className="hidden text-left sm:block">
            <span className="block max-w-36 truncate text-[13px] leading-tight font-medium text-foreground">
              {user.name}
            </span>
            <span className="block max-w-36 truncate text-xs text-muted">{user.email}</span>
          </span>
        </span>
      }
    >
      <div className="px-3 py-2 sm:hidden">
        <p className="truncate text-sm font-medium text-foreground">{user.name}</p>
        <p className="truncate text-xs text-muted">{user.email}</p>
      </div>
      <DropdownSeparator />
      <DropdownItem icon={LogOut} danger onSelect={() => logout.mutate()}>
        Sair da conta
      </DropdownItem>
    </Dropdown>
  )
}

function AppLayoutShell() {
  const sidebarOpen = useUiStore((state) => state.sidebarOpen)
  const sidebarCollapsed = useUiStore((state) => state.sidebarCollapsed)
  const closeSidebar = useUiStore((state) => state.closeSidebar)
  const openSidebar = useUiStore((state) => state.openSidebar)
  const toggleSidebarCollapsed = useUiStore((state) => state.toggleSidebarCollapsed)
  const topbarSlot = useTopbarSlotContent()

  return (
    <div className="flex h-dvh overflow-hidden">
      <div className="z-20 hidden shrink-0 shadow-card lg:block">
        <SidebarNavigation collapsed={sidebarCollapsed} />
      </div>

      {sidebarOpen && (
        <div
          className="animate-fade-in fixed inset-0 z-40 bg-overlay lg:hidden"
          onClick={closeSidebar}
          aria-hidden="true"
        />
      )}
      <div
        className={cn(
          'fixed inset-y-0 left-0 z-50 shadow-pop transition-transform duration-200 lg:hidden',
          sidebarOpen ? 'translate-x-0' : '-translate-x-full',
        )}
      >
        <SidebarNavigation onNavigate={closeSidebar} />
      </div>

      <div className="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <Topbar>
          <button
            type="button"
            onClick={openSidebar}
            aria-label="Abrir menu"
            className="flex size-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:hidden"
          >
            <Menu className="size-4.5" />
          </button>
          <button
            type="button"
            onClick={toggleSidebarCollapsed}
            aria-label={sidebarCollapsed ? 'Mostrar menu' : 'Esconder menu'}
            className="hidden size-8 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:flex"
          >
            <Menu className="size-4.5" />
          </button>
          {topbarSlot ? (
            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">{topbarSlot}</div>
          ) : (
            <div className="flex-1" aria-hidden="true" />
          )}
          <div className="ml-auto flex shrink-0 items-center gap-1.5">
            <TenantSelector />
            <ThemeToggle />
            <UserMenu />
          </div>
        </Topbar>

        <main className="flex min-h-0 flex-1 flex-col overflow-hidden">
          <Container className="flex min-h-0 flex-1 flex-col overflow-hidden pt-1.5 pb-2">
            <Suspense fallback={<Loading />}>
              <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                <Outlet />
              </div>
            </Suspense>
          </Container>
        </main>
      </div>

    </div>
  )
}

export function AppLayout() {
  return (
    <TopbarSlotProvider>
      <AppLayoutShell />
    </TopbarSlotProvider>
  )
}
