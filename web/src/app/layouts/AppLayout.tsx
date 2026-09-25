import { Suspense } from 'react'
import { Outlet, useLocation } from 'react-router'
import { Building2, BellRing, Car, ClipboardList, Contact, Cpu, CreditCard, FileBarChart, FileText, Hexagon, History, IdCard, LayoutDashboard, LogOut, MapPin, MapPinned, Menu, Package, Receipt, Repeat, ScrollText, Search, Send, Settings, ShieldCheck, Users, Wallet, Wrench } from 'lucide-react'
import { AppLogo } from '@/shared/brand/AppLogo'
import { useActiveTenant } from '@/shared/brand/useActiveTenant'
import { TenantSelector } from '@/modules/auth/components/TenantSelector'
import { NotificationBell } from '@/modules/notifications/components/NotificationBell'
import { useSessionStore } from '@/shared/stores/session.store'
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
  const user = useSessionStore((state) => state.user)
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
  const showServices = isOperatingTenant && can(Permission.SERVICE_READ)
  const showContracts = isOperatingTenant && can(Permission.CONTRACT_READ)
  const showVehicles = isOperatingTenant && can(Permission.VEHICLE_READ)
  const showVehicleDataConfig = isOperatingTenant && can(Permission.VEHICLE_DATA_CONFIG_READ)
  const showEquipments = isOperatingTenant && can(Permission.EQUIPMENT_READ)
  const showTracking = isOperatingTenant && can(Permission.TRACKING_READ)
  const showReports = isOperatingTenant && can(Permission.REPORT_VIEW)
  const showGeofences = isOperatingTenant && can(Permission.GEOFENCE_READ)
  const showPois = isOperatingTenant && can(Permission.POI_READ)
  const showAlerts = isOperatingTenant && can(Permission.ALERT_READ)
  const showAlertPreferences = showAlerts && user?.client_id != null
  const showNotificationSend = isOperatingTenant && can(Permission.NOTIFICATION_SEND)
  const showServiceOrders = isOperatingTenant && can(Permission.SERVICE_ORDER_READ)
  const showCadastrosGroup =
    showClients || showDrivers || showServices || showContracts || showVehicles || showEquipments
  const showGeoGroup = showGeofences || showPois
  const showAlertsGroup = showAlerts || showNotificationSend
  const showOpsGroup = showServiceOrders

  const showFinanceDashboard = isOperatingTenant && can(Permission.FINANCE_DASHBOARD_READ)
  const showFinancePlans = isOperatingTenant && can(Permission.FINANCE_PLAN_READ)
  const showFinanceBillings = isOperatingTenant && can(Permission.FINANCE_BILLING_READ)
  const showFinanceSubscriptions = isOperatingTenant && can(Permission.FINANCE_SUBSCRIPTION_READ)
  const showFinanceReports = isOperatingTenant && can(Permission.FINANCE_REPORT_READ)
  const showFinanceGateway = isOperatingTenant && can(Permission.FINANCE_GATEWAY_CONFIG_READ)
  const showFinanceOperatorGroup =
    showFinanceDashboard ||
    showFinancePlans ||
    showFinanceBillings ||
    showFinanceSubscriptions ||
    showFinanceReports ||
    showFinanceGateway
  const showFinancePortal = isOperatingTenant && can(Permission.FINANCE_PORTAL_VIEW) && !showFinanceOperatorGroup

  return (
    <Sidebar header={<Brand collapsed={collapsed} />} collapsed={collapsed}>
      <SidebarGroup label="Geral">
        <SidebarItem to="/dashboard" icon={LayoutDashboard} label="Dashboard" onNavigate={onNavigate} />
        {showTracking && (
          <SidebarItem to="/monitoring" icon={MapPinned} label="Monitoramento" onNavigate={onNavigate} />
        )}
        {showReports && (
          <SidebarItem to="/reports" icon={FileBarChart} label="Relatórios" onNavigate={onNavigate} />
        )}
        {/* Assistente de IA oculto no frontend
        {can(Permission.ASSISTANT_VIEW) && (
          <SidebarItem to="/assistant" icon={Sparkles} label="Assistente de IA" onNavigate={onNavigate} />
        )}
        */}
      </SidebarGroup>

      {showOpsGroup && (
        <SidebarGroup label="Operação">
          {showServiceOrders && (
            <SidebarItem
              to="/service-orders"
              icon={ClipboardList}
              label="Ordens de serviço"
              onNavigate={onNavigate}
            />
          )}
        </SidebarGroup>
      )}
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

      {showFinanceOperatorGroup && (
        <SidebarGroup label="Financeiro">
          {showFinanceDashboard && (
            <SidebarItem
              to="/finance/dashboard"
              icon={Wallet}
              label="Dashboard"
              onNavigate={onNavigate}
            />
          )}
          {showFinancePlans && (
            <SidebarItem to="/finance/plans" icon={Package} label="Planos" onNavigate={onNavigate} />
          )}
          {showFinanceSubscriptions && (
            <SidebarItem
              to="/finance/subscriptions"
              icon={Repeat}
              label="Assinaturas"
              onNavigate={onNavigate}
            />
          )}
          {showFinanceBillings && (
            <SidebarItem
              to="/finance/billings"
              icon={Receipt}
              label="Cobranças"
              onNavigate={onNavigate}
            />
          )}
          {showFinanceReports && (
            <SidebarItem
              to="/finance/reports"
              icon={FileBarChart}
              label="Relatórios"
              onNavigate={onNavigate}
            />
          )}
          {showFinanceGateway && (
            <SidebarItem
              to="/finance/gateway-config"
              icon={CreditCard}
              label="Gateway"
              onNavigate={onNavigate}
            />
          )}
        </SidebarGroup>
      )}

      {showFinancePortal && (
        <SidebarGroup label="Financeiro">
          <SidebarItem
            to="/finance/portal/billings"
            icon={Receipt}
            label="Minhas faturas"
            onNavigate={onNavigate}
          />
          <SidebarItem
            to="/finance/portal/subscription"
            icon={Repeat}
            label="Assinatura"
            onNavigate={onNavigate}
          />
          <SidebarItem
            to="/finance/portal/history"
            icon={History}
            label="Histórico"
            onNavigate={onNavigate}
          />
        </SidebarGroup>
      )}

      {showCadastrosGroup && (
        <SidebarGroup label="Cadastros">
          {showClients && (
            <SidebarItem to="/clients" icon={Contact} label="Clientes" onNavigate={onNavigate} />
          )}
          {showDrivers && (
            <SidebarItem to="/drivers" icon={IdCard} label="Motoristas" onNavigate={onNavigate} />
          )}
          {showServices && (
            <SidebarItem to="/services" icon={Wrench} label="Serviços" onNavigate={onNavigate} />
          )}
          {showContracts && (
            <SidebarItem to="/contracts" icon={FileText} label="Contratos" onNavigate={onNavigate} />
          )}
          {showVehicles && (
            <SidebarItem to="/vehicles" icon={Car} label="Veículos" onNavigate={onNavigate} />
          )}
          {showVehicleDataConfig && (
            <SidebarItem
              to="/vehicle-data/config"
              icon={Search}
              label="Consulta de placa"
              onNavigate={onNavigate}
            />
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
          {showAlertPreferences && (
            <SidebarItem
              to="/alerts/preferences"
              icon={BellRing}
              label="Meus alertas"
              onNavigate={onNavigate}
            />
          )}
          {showNotificationSend && (
            <SidebarItem to="/notifications/send" icon={Send} label="Enviar notificação" onNavigate={onNavigate} />
          )}
          {showNotificationSend && (
            <SidebarItem to="/notifications/sent" icon={History} label="Histórico" onNavigate={onNavigate} />
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
        {can(Permission.TENANT_UPDATE) && (
          <SidebarItem to="/settings" icon={Settings} label="Configurações" onNavigate={onNavigate} />
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
  const sidebarCollapsed = useUiStore((state) => state.sidebarCollapsed)
  const closeSidebar = useUiStore((state) => state.closeSidebar)
  const openSidebar = useUiStore((state) => state.openSidebar)
  const toggleSidebarCollapsed = useUiStore((state) => state.toggleSidebarCollapsed)
  const isMonitoring = location.pathname === '/monitoring'

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
          <button
            type="button"
            onClick={toggleSidebarCollapsed}
            aria-label={sidebarCollapsed ? 'Mostrar menu' : 'Esconder menu'}
            className="hidden size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground lg:flex"
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
