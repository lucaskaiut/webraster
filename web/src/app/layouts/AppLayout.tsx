import { Suspense } from 'react'
import { Outlet, useLocation } from 'react-router'
import { Building2, BellRing, Car, Contact, Cpu, Hexagon, History, IdCard, LayoutDashboard, LogOut, MapPin, MapPinned, Menu, ScrollText, Settings2, ShieldCheck, Users, Zap } from 'lucide-react'
import { TenantSelector } from '@/modules/auth/components/TenantSelector'
import { NotificationBell } from '@/modules/notifications/components/NotificationBell'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import { useUiStore } from '@/shared/stores/ui.store'
import { Permission } from '@/shared/constants/permissions'
import { useIsUmbrellaTenant } from '@/shared/hooks/useIsUmbrellaTenant'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useLogout } from '@/modules/auth/hooks/useAuth'
// import { AssistantWidget } from '@/modules/assistant/components/AssistantWidget'
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

function Brand() {
  const tenant = useSessionStore((state) => state.tenant)
  const isMaster = useSessionStore((state) => state.isMaster)
  const availableTenants = useSessionStore((state) => state.availableTenants)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)

  const activeName = isMaster
    ? (availableTenants.find((item) => item.id === selectedTenantId)?.name ?? tenant?.name)
    : tenant?.name

  return (
    <div className="flex items-center gap-2.5 px-1">
      <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-card">
        <Zap className="size-4.5" aria-hidden="true" />
      </span>
      <span className="min-w-0">
        <span className="block text-sm leading-tight font-semibold text-foreground">Nox</span>
        <span className="block truncate text-xs text-muted">{activeName}</span>
      </span>
    </div>
  )
}

function SidebarNavigation({ onNavigate }: { onNavigate?: () => void }) {
  const { can } = usePermissions()
  const isUmbrella = useIsUmbrellaTenant()
  /** Funcionalidades de uso final só no tenant filho (empresa operacional). */
  const isOperatingTenant = !isUmbrella

  // Controle de assinatura desabilitado neste sistema
  // const showPlans = isUmbrella && can(Permission.PLAN_READ)
  // const showSubscription = isOperatingTenant && can(Permission.SUBSCRIPTION_READ)
  // const showInvoices = isOperatingTenant && can(Permission.INVOICE_READ)
  // const showBillingGroup = showPlans || showSubscription || showInvoices

  const showClients = isOperatingTenant && can(Permission.CLIENT_READ)
  const showDrivers = isOperatingTenant && can(Permission.DRIVER_READ)
  const showVehicles = isOperatingTenant && can(Permission.VEHICLE_READ)
  const showEquipments = isOperatingTenant && can(Permission.EQUIPMENT_READ)
  const showTracking = isOperatingTenant && can(Permission.TRACKING_READ)
  const showGeofences = isOperatingTenant && can(Permission.GEOFENCE_READ)
  const showPois = isOperatingTenant && can(Permission.POI_READ)
  const showAlerts = isOperatingTenant && can(Permission.ALERT_READ)
  const showAlertConfig = isOperatingTenant && can(Permission.ALERT_CONFIG_READ)
  const showCadastrosGroup = showClients || showDrivers || showVehicles || showEquipments
  const showGeoGroup = showGeofences || showPois
  const showAlertsGroup = showAlerts || showAlertConfig

  return (
    <Sidebar header={<Brand />}>
      <SidebarGroup label="Geral">
        <SidebarItem to="/dashboard" icon={LayoutDashboard} label="Dashboard" onNavigate={onNavigate} />
        {showTracking && (
          <SidebarItem to="/monitoring" icon={MapPinned} label="Monitoramento" onNavigate={onNavigate} />
        )}
        {/* Assistente de IA oculto no frontend
        {can(Permission.ASSISTANT_VIEW) && (
          <SidebarItem to="/assistant" icon={Sparkles} label="Assistente de IA" onNavigate={onNavigate} />
        )}
        */}
      </SidebarGroup>

      {/* Controle de assinatura desabilitado neste sistema
      {showBillingGroup && (
        <SidebarGroup label="Assinaturas">
          {showPlans && (
            <SidebarItem to="/billing/plans" icon={CreditCard} label="Planos" onNavigate={onNavigate} />
          )}
          {showSubscription && (
            <SidebarItem
              to="/billing/subscription"
              icon={CreditCard}
              label="Minha assinatura"
              onNavigate={onNavigate}
            />
          )}
          {showInvoices && (
            <SidebarItem
              to="/billing/invoices"
              icon={Receipt}
              label="Cobranças"
              onNavigate={onNavigate}
            />
          )}
        </SidebarGroup>
      )}
      */}

      {showCadastrosGroup && (
        <SidebarGroup label="Cadastros">
          {showClients && (
            <SidebarItem to="/clients" icon={Contact} label="Clientes" onNavigate={onNavigate} />
          )}
          {showDrivers && (
            <SidebarItem to="/drivers" icon={IdCard} label="Motoristas" onNavigate={onNavigate} />
          )}
          {showVehicles && (
            <SidebarItem to="/vehicles" icon={Car} label="Veículos" onNavigate={onNavigate} />
          )}
          {showEquipments && (
            <SidebarItem to="/equipments" icon={Cpu} label="Equipamentos" onNavigate={onNavigate} />
          )}
        </SidebarGroup>
      )}

      {showGeoGroup && (
        <SidebarGroup label="Geocercas e POIs">
          {showGeofences && (
            <SidebarItem to="/geofences" icon={Hexagon} label="Geocercas" onNavigate={onNavigate} />
          )}
          {showGeofences && (
            <SidebarItem to="/geofence-events" icon={History} label="Eventos" onNavigate={onNavigate} />
          )}
          {showPois && (
            <SidebarItem to="/pois" icon={MapPin} label="POIs" onNavigate={onNavigate} />
          )}
        </SidebarGroup>
      )}

      {showAlertsGroup && (
        <SidebarGroup label="Alertas">
          {showAlerts && (
            <SidebarItem to="/alerts" icon={BellRing} label="Alertas" onNavigate={onNavigate} />
          )}
          {showAlertConfig && (
            <SidebarItem to="/alert-configs" icon={Settings2} label="Configuração" onNavigate={onNavigate} />
          )}
        </SidebarGroup>
      )}

      <SidebarGroup label="Gestão">
        {isUmbrella && can(Permission.TENANT_READ) && (
          <SidebarItem to="/tenants" icon={Building2} label="Empresas" onNavigate={onNavigate} />
        )}
        {can(Permission.USER_READ) && (
          <SidebarItem to="/users" icon={Users} label="Usuários" onNavigate={onNavigate} />
        )}
        {can(Permission.ROLE_READ) && (
          <SidebarItem to="/roles" icon={ShieldCheck} label="Perfis de acesso" onNavigate={onNavigate} />
        )}
        {/* Tokens de API e Webhooks ocultos no frontend
        {can(Permission.API_TOKEN_READ) && (
          <SidebarItem to="/api-tokens" icon={KeyRound} label="Tokens de API" onNavigate={onNavigate} />
        )}
        {can(Permission.WEBHOOK_READ) && (
          <SidebarItem to="/webhooks" icon={Webhook} label="Webhooks" onNavigate={onNavigate} />
        )}
        */}
        {can(Permission.AUDIT_VIEW) && (
          <SidebarItem to="/audit" icon={ScrollText} label="Auditoria" onNavigate={onNavigate} />
        )}
      </SidebarGroup>

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

export function AppLayout() {
  const location = useLocation()
  const sidebarOpen = useUiStore((state) => state.sidebarOpen)
  const closeSidebar = useUiStore((state) => state.closeSidebar)
  const openSidebar = useUiStore((state) => state.openSidebar)
  const isMonitoring = location.pathname === '/monitoring'

  return (
    <div className="flex h-dvh overflow-hidden">
      <div className="z-20 hidden shrink-0 shadow-card lg:block">
        <SidebarNavigation />
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

      <div className={cn('flex min-w-0 flex-1 flex-col', isMonitoring ? 'overflow-hidden' : 'overflow-y-auto')}>
        <Topbar>
          <button
            type="button"
            onClick={openSidebar}
            aria-label="Abrir menu"
            className="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:hidden"
          >
            <Menu className="size-5" />
          </button>
          <div className="ml-auto flex items-center gap-1.5">
            <NotificationBell />
            <TenantSelector />
            <ThemeToggle />
            <UserMenu />
          </div>
        </Topbar>

        <main className={cn('flex-1', isMonitoring && 'flex min-h-0 flex-col overflow-hidden')}>
          <Container className={cn('pt-2', isMonitoring && 'flex min-h-0 flex-1 flex-col px-3 pb-3 lg:px-4')}>
            <Suspense fallback={<Loading />}>
              <Outlet />
            </Suspense>
          </Container>
        </main>
      </div>

      {/* Assistente de IA oculto no frontend
      {can(Permission.ASSISTANT_VIEW) && <AssistantWidget />}
      */}
    </div>
  )
}
