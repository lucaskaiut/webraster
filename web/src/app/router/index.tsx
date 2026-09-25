import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { AuthGuard } from '@/app/guards/AuthGuard'
import { GuestGuard } from '@/app/guards/GuestGuard'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AppLayout } from '@/app/layouts/AppLayout'
import { AuthLayout } from '@/app/layouts/AuthLayout'
import { Permission } from '@/shared/constants/permissions'
import { NotFoundPage } from './NotFoundPage'

const LoginPage = lazy(() => import('@/modules/auth/pages/LoginPage'))
const RegisterPage = lazy(() => import('@/modules/auth/pages/RegisterPage'))
const ForgotPasswordPage = lazy(() => import('@/modules/auth/pages/ForgotPasswordPage'))
const ResetPasswordPage = lazy(() => import('@/modules/auth/pages/ResetPasswordPage'))
const PaymentPendingPage = lazy(() => import('@/modules/auth/pages/PaymentPendingPage'))
const DashboardPage = lazy(() => import('@/modules/dashboard/pages/DashboardPage'))
const UsersListPage = lazy(() => import('@/modules/users/pages/UsersListPage'))
const UserCreatePage = lazy(() => import('@/modules/users/pages/UserCreatePage'))
const UserEditPage = lazy(() => import('@/modules/users/pages/UserEditPage'))
const RolesListPage = lazy(() => import('@/modules/roles/pages/RolesListPage'))
const RoleCreatePage = lazy(() => import('@/modules/roles/pages/RoleCreatePage'))
const RoleEditPage = lazy(() => import('@/modules/roles/pages/RoleEditPage'))
// Tokens de API e Webhooks ocultos no frontend
// const ApiTokensListPage = lazy(() => import('@/modules/api-tokens/pages/ApiTokensListPage'))
// const ApiTokenCreatePage = lazy(() => import('@/modules/api-tokens/pages/ApiTokenCreatePage'))
// const WebhooksListPage = lazy(() => import('@/modules/webhooks/pages/WebhooksListPage'))
// const WebhookCreatePage = lazy(() => import('@/modules/webhooks/pages/WebhookCreatePage'))
// const WebhookEditPage = lazy(() => import('@/modules/webhooks/pages/WebhookEditPage'))
// Controle de assinatura desabilitado neste sistema
// const PlansListPage = lazy(() => import('@/modules/billing/pages/PlansListPage'))
// const PlanCreatePage = lazy(() => import('@/modules/billing/pages/PlanCreatePage'))
// const PlanEditPage = lazy(() => import('@/modules/billing/pages/PlanEditPage'))
// const SubscriptionPage = lazy(() => import('@/modules/billing/pages/SubscriptionPage'))
// const InvoicesListPage = lazy(() => import('@/modules/billing/pages/InvoicesListPage'))
const AuditPage = lazy(() => import('@/modules/audit/pages/AuditPage'))
// const AssistantPage = lazy(() => import('@/modules/assistant/pages/AssistantPage'))
const TenantsListPage = lazy(() => import('@/modules/tenants/pages/TenantsListPage'))
const TenantCreatePage = lazy(() => import('@/modules/tenants/pages/TenantCreatePage'))
const TenantEditPage = lazy(() => import('@/modules/tenants/pages/TenantEditPage'))
const TenantSettingsPage = lazy(() => import('@/modules/tenants/pages/TenantSettingsPage'))
const ClientsListPage = lazy(() => import('@/modules/clients/pages/ClientsListPage'))
const ClientCreatePage = lazy(() => import('@/modules/clients/pages/ClientCreatePage'))
const ClientEditPage = lazy(() => import('@/modules/clients/pages/ClientEditPage'))
const DriversListPage = lazy(() => import('@/modules/drivers/pages/DriversListPage'))
const DriverCreatePage = lazy(() => import('@/modules/drivers/pages/DriverCreatePage'))
const DriverEditPage = lazy(() => import('@/modules/drivers/pages/DriverEditPage'))
const ServicesListPage = lazy(() => import('@/modules/services/pages/ServicesListPage'))
const ServiceCreatePage = lazy(() => import('@/modules/services/pages/ServiceCreatePage'))
const ServiceEditPage = lazy(() => import('@/modules/services/pages/ServiceEditPage'))
const ContractsListPage = lazy(() => import('@/modules/contracts/pages/ContractsListPage'))
const ContractCreatePage = lazy(() => import('@/modules/contracts/pages/ContractCreatePage'))
const ContractEditPage = lazy(() => import('@/modules/contracts/pages/ContractEditPage'))
const VehiclesListPage = lazy(() => import('@/modules/vehicles/pages/VehiclesListPage'))
const VehicleCreatePage = lazy(() => import('@/modules/vehicles/pages/VehicleCreatePage'))
const VehicleEditPage = lazy(() => import('@/modules/vehicles/pages/VehicleEditPage'))
const VehicleDataConfigPage = lazy(() => import('@/modules/vehicles/pages/VehicleDataConfigPage'))
const EquipmentsListPage = lazy(() => import('@/modules/equipments/pages/EquipmentsListPage'))
const EquipmentCreatePage = lazy(() => import('@/modules/equipments/pages/EquipmentCreatePage'))
const EquipmentEditPage = lazy(() => import('@/modules/equipments/pages/EquipmentEditPage'))
const MonitoringPage = lazy(() => import('@/modules/tracking/pages/MonitoringPage'))
const GeofencesListPage = lazy(() => import('@/modules/geofences/pages/GeofencesListPage'))
const GeofenceCreatePage = lazy(() => import('@/modules/geofences/pages/GeofenceCreatePage'))
const GeofenceEditPage = lazy(() => import('@/modules/geofences/pages/GeofenceEditPage'))
const GeofenceEventsPage = lazy(() => import('@/modules/geofences/pages/GeofenceEventsPage'))
const PoisListPage = lazy(() => import('@/modules/pois/pages/PoisListPage'))
const PoiCreatePage = lazy(() => import('@/modules/pois/pages/PoiCreatePage'))
const PoiEditPage = lazy(() => import('@/modules/pois/pages/PoiEditPage'))
const AlertsListPage = lazy(() => import('@/modules/alerts/pages/AlertsListPage'))
const AlertConfigPage = lazy(() => import('@/modules/alerts/pages/AlertConfigPage'))
const NotificationSendPage = lazy(() => import('@/modules/notifications/pages/NotificationSendPage'))
const NotificationHistoryPage = lazy(() => import('@/modules/notifications/pages/NotificationHistoryPage'))
const ServiceOrdersListPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrdersListPage'))
const ServiceOrderCreatePage = lazy(() => import('@/modules/service-orders/pages/ServiceOrderCreatePage'))
const ServiceOrderEditPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrderEditPage'))
const ServiceOrderDetailPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrderDetailPage'))
const ServiceOrdersKanbanPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrdersKanbanPage'))
const ServiceOrdersCalendarPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrdersCalendarPage'))
const ReportsPage = lazy(() => import('@/modules/reports/pages/ReportsPage'))

const FinanceDashboardPage = lazy(() => import('@/modules/finance/pages/FinanceDashboardPage'))
const FinancePlansListPage = lazy(() => import('@/modules/finance/pages/FinancePlansListPage'))
const FinancePlanCreatePage = lazy(() => import('@/modules/finance/pages/FinancePlanCreatePage'))
const FinancePlanEditPage = lazy(() => import('@/modules/finance/pages/FinancePlanEditPage'))
const FinanceSubscriptionsListPage = lazy(() => import('@/modules/finance/pages/FinanceSubscriptionsListPage'))
const FinanceBillingsListPage = lazy(() => import('@/modules/finance/pages/FinanceBillingsListPage'))
const FinanceBillingDetailPage = lazy(() => import('@/modules/finance/pages/FinanceBillingDetailPage'))
const FinanceReportsPage = lazy(() => import('@/modules/finance/pages/FinanceReportsPage'))
const FinanceGatewayConfigPage = lazy(() => import('@/modules/finance/pages/FinanceGatewayConfigPage'))
const FinancePortalBillingsPage = lazy(() => import('@/modules/finance/pages/FinancePortalBillingsPage'))
const FinancePortalBillingDetailPage = lazy(
  () => import('@/modules/finance/pages/FinancePortalBillingDetailPage'),
)
const FinancePortalSubscriptionPage = lazy(
  () => import('@/modules/finance/pages/FinancePortalSubscriptionPage'),
)
const FinancePortalHistoryPage = lazy(() => import('@/modules/finance/pages/FinancePortalHistoryPage'))

export const router = createBrowserRouter([
  {
    element: <GuestGuard />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          { path: '/auth/login', element: <LoginPage /> },
          { path: '/auth/register', element: <RegisterPage /> },
          { path: '/auth/forgot-password', element: <ForgotPasswordPage /> },
          { path: '/auth/reset-password', element: <ResetPasswordPage /> },
        ],
      },
    ],
  },
  {
    element: <AuthGuard />,
    children: [
      {
        path: '/pagamento',
        element: <PaymentPendingPage />,
      },
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to="/dashboard" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          {
            path: '/monitoring',
            element: (
              <PermissionGuard permission={Permission.TRACKING_READ} requiresChildTenant>
                <MonitoringPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users',
            element: (
              <PermissionGuard permission={Permission.USER_READ}>
                <UsersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/create',
            element: (
              <PermissionGuard permission={Permission.USER_CREATE}>
                <UserCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/:id/edit',
            element: (
              <PermissionGuard permission={Permission.USER_UPDATE}>
                <UserEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles',
            element: (
              <PermissionGuard permission={Permission.ROLE_READ}>
                <RolesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/create',
            element: (
              <PermissionGuard permission={Permission.ROLE_CREATE}>
                <RoleCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/:id/edit',
            element: (
              <PermissionGuard permission={Permission.ROLE_UPDATE}>
                <RoleEditPage />
              </PermissionGuard>
            ),
          },
          // Tokens de API e Webhooks ocultos no frontend
          // {
          //   path: '/api-tokens',
          //   element: (
          //     <PermissionGuard permission={Permission.API_TOKEN_READ}>
          //       <ApiTokensListPage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/api-tokens/create',
          //   element: (
          //     <PermissionGuard permission={Permission.API_TOKEN_CREATE}>
          //       <ApiTokenCreatePage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/webhooks',
          //   element: (
          //     <PermissionGuard permission={Permission.WEBHOOK_READ}>
          //       <WebhooksListPage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/webhooks/create',
          //   element: (
          //     <PermissionGuard permission={Permission.WEBHOOK_CREATE}>
          //       <WebhookCreatePage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/webhooks/:id/edit',
          //   element: (
          //     <PermissionGuard permission={Permission.WEBHOOK_UPDATE}>
          //       <WebhookEditPage />
          //     </PermissionGuard>
          //   ),
          // },
          // Controle de assinatura desabilitado neste sistema
          // {
          //   path: '/billing/plans',
          //   element: (
          //     <PermissionGuard permission={Permission.PLAN_READ} requiresUmbrella>
          //       <PlansListPage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/billing/plans/create',
          //   element: (
          //     <PermissionGuard permission={Permission.PLAN_CREATE} requiresUmbrella>
          //       <PlanCreatePage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/billing/plans/:id/edit',
          //   element: (
          //     <PermissionGuard permission={Permission.PLAN_UPDATE} requiresUmbrella>
          //       <PlanEditPage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/billing/subscription',
          //   element: (
          //     <PermissionGuard permission={Permission.SUBSCRIPTION_READ} requiresChildTenant>
          //       <SubscriptionPage />
          //     </PermissionGuard>
          //   ),
          // },
          // {
          //   path: '/billing/invoices',
          //   element: (
          //     <PermissionGuard permission={Permission.INVOICE_READ} requiresChildTenant>
          //       <InvoicesListPage />
          //     </PermissionGuard>
          //   ),
          // },
          {
            path: '/audit',
            element: (
              <PermissionGuard permission={Permission.AUDIT_VIEW}>
                <AuditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/tenants',
            element: (
              <PermissionGuard permission={Permission.TENANT_READ} requiresUmbrella>
                <TenantsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/tenants/create',
            element: (
              <PermissionGuard permission={Permission.TENANT_CREATE} requiresUmbrella>
                <TenantCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/tenants/:id/edit',
            element: (
              <PermissionGuard permission={Permission.TENANT_UPDATE} requiresUmbrella>
                <TenantEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/settings',
            element: (
              <PermissionGuard permission={Permission.TENANT_UPDATE}>
                <TenantSettingsPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/clients',
            element: (
              <PermissionGuard permission={Permission.CLIENT_READ} requiresChildTenant>
                <ClientsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/clients/create',
            element: (
              <PermissionGuard permission={Permission.CLIENT_CREATE} requiresChildTenant>
                <ClientCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/clients/:id/edit',
            element: (
              <PermissionGuard permission={Permission.CLIENT_UPDATE} requiresChildTenant>
                <ClientEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/drivers',
            element: (
              <PermissionGuard permission={Permission.DRIVER_READ} requiresChildTenant>
                <DriversListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/drivers/create',
            element: (
              <PermissionGuard permission={Permission.DRIVER_CREATE} requiresChildTenant>
                <DriverCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/drivers/:id/edit',
            element: (
              <PermissionGuard permission={Permission.DRIVER_UPDATE} requiresChildTenant>
                <DriverEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/services',
            element: (
              <PermissionGuard permission={Permission.SERVICE_READ} requiresChildTenant>
                <ServicesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/services/create',
            element: (
              <PermissionGuard permission={Permission.SERVICE_CREATE} requiresChildTenant>
                <ServiceCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/services/:id/edit',
            element: (
              <PermissionGuard permission={Permission.SERVICE_UPDATE} requiresChildTenant>
                <ServiceEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/contracts',
            element: (
              <PermissionGuard permission={Permission.CONTRACT_READ} requiresChildTenant>
                <ContractsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/contracts/create',
            element: (
              <PermissionGuard permission={Permission.CONTRACT_CREATE} requiresChildTenant>
                <ContractCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/contracts/:id/edit',
            element: (
              <PermissionGuard permission={Permission.CONTRACT_UPDATE} requiresChildTenant>
                <ContractEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/vehicles',
            element: (
              <PermissionGuard permission={Permission.VEHICLE_READ} requiresChildTenant>
                <VehiclesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/vehicles/create',
            element: (
              <PermissionGuard permission={Permission.VEHICLE_CREATE} requiresChildTenant>
                <VehicleCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/vehicles/:id/edit',
            element: (
              <PermissionGuard permission={Permission.VEHICLE_UPDATE} requiresChildTenant>
                <VehicleEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/vehicle-data/config',
            element: (
              <PermissionGuard
                permission={Permission.VEHICLE_DATA_CONFIG_READ}
                requiresChildTenant
              >
                <VehicleDataConfigPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/equipments',
            element: (
              <PermissionGuard permission={Permission.EQUIPMENT_READ} requiresChildTenant>
                <EquipmentsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/equipments/create',
            element: (
              <PermissionGuard permission={Permission.EQUIPMENT_CREATE} requiresChildTenant>
                <EquipmentCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/equipments/:id/edit',
            element: (
              <PermissionGuard permission={Permission.EQUIPMENT_UPDATE} requiresChildTenant>
                <EquipmentEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/geofences',
            element: (
              <PermissionGuard permission={Permission.GEOFENCE_READ} requiresChildTenant>
                <GeofencesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/geofences/create',
            element: (
              <PermissionGuard permission={Permission.GEOFENCE_CREATE} requiresChildTenant>
                <GeofenceCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/geofences/:id/edit',
            element: (
              <PermissionGuard permission={Permission.GEOFENCE_UPDATE} requiresChildTenant>
                <GeofenceEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/geofence-events',
            element: (
              <PermissionGuard permission={Permission.GEOFENCE_READ} requiresChildTenant>
                <GeofenceEventsPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/pois',
            element: (
              <PermissionGuard permission={Permission.POI_READ} requiresChildTenant>
                <PoisListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/pois/create',
            element: (
              <PermissionGuard permission={Permission.POI_CREATE} requiresChildTenant>
                <PoiCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/pois/:id/edit',
            element: (
              <PermissionGuard permission={Permission.POI_UPDATE} requiresChildTenant>
                <PoiEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/alerts',
            element: (
              <PermissionGuard permission={Permission.ALERT_READ} requiresChildTenant>
                <AlertsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/alert-configs',
            element: (
              <PermissionGuard permission={Permission.ALERT_CONFIG_READ} requiresChildTenant>
                <AlertConfigPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/notifications/send',
            element: (
              <PermissionGuard permission={Permission.NOTIFICATION_SEND} requiresChildTenant>
                <NotificationSendPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/notifications/sent',
            element: (
              <PermissionGuard permission={Permission.NOTIFICATION_SEND} requiresChildTenant>
                <NotificationHistoryPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/service-orders',
            element: (
              <PermissionGuard permission={Permission.SERVICE_ORDER_READ} requiresChildTenant>
                <ServiceOrdersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/service-orders/kanban',
            element: (
              <PermissionGuard permission={Permission.SERVICE_ORDER_READ} requiresChildTenant>
                <ServiceOrdersKanbanPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/service-orders/calendar',
            element: (
              <PermissionGuard permission={Permission.SERVICE_ORDER_READ} requiresChildTenant>
                <ServiceOrdersCalendarPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/service-orders/create',
            element: (
              <PermissionGuard permission={Permission.SERVICE_ORDER_CREATE} requiresChildTenant>
                <ServiceOrderCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/service-orders/:id',
            element: (
              <PermissionGuard permission={Permission.SERVICE_ORDER_READ} requiresChildTenant>
                <ServiceOrderDetailPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/service-orders/:id/edit',
            element: (
              <PermissionGuard permission={Permission.SERVICE_ORDER_UPDATE} requiresChildTenant>
                <ServiceOrderEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/reports',
            element: (
              <PermissionGuard permission={Permission.REPORT_VIEW} requiresChildTenant>
                <ReportsPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/dashboard',
            element: (
              <PermissionGuard permission={Permission.FINANCE_DASHBOARD_READ} requiresChildTenant>
                <FinanceDashboardPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/plans',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PLAN_READ} requiresChildTenant>
                <FinancePlansListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/plans/create',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PLAN_CREATE} requiresChildTenant>
                <FinancePlanCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/plans/:id/edit',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PLAN_UPDATE} requiresChildTenant>
                <FinancePlanEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/subscriptions',
            element: (
              <PermissionGuard permission={Permission.FINANCE_SUBSCRIPTION_READ} requiresChildTenant>
                <FinanceSubscriptionsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/billings',
            element: (
              <PermissionGuard permission={Permission.FINANCE_BILLING_READ} requiresChildTenant>
                <FinanceBillingsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/billings/:id',
            element: (
              <PermissionGuard permission={Permission.FINANCE_BILLING_READ} requiresChildTenant>
                <FinanceBillingDetailPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/reports',
            element: (
              <PermissionGuard permission={Permission.FINANCE_REPORT_READ} requiresChildTenant>
                <FinanceReportsPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/gateway-config',
            element: (
              <PermissionGuard permission={Permission.FINANCE_GATEWAY_CONFIG_READ} requiresChildTenant>
                <FinanceGatewayConfigPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/portal/billings',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PORTAL_VIEW} requiresChildTenant>
                <FinancePortalBillingsPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/portal/billings/:id',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PORTAL_VIEW} requiresChildTenant>
                <FinancePortalBillingDetailPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/portal/subscription',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PORTAL_VIEW} requiresChildTenant>
                <FinancePortalSubscriptionPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/finance/portal/history',
            element: (
              <PermissionGuard permission={Permission.FINANCE_PORTAL_VIEW} requiresChildTenant>
                <FinancePortalHistoryPage />
              </PermissionGuard>
            ),
          },
        ],
      },
      // Assistente de IA oculto no frontend
      // {
      //   path: '/assistant/:id?',
      //   element: (
      //     <PermissionGuard permission={Permission.ASSISTANT_VIEW}>
      //       <AssistantPage />
      //     </PermissionGuard>
      //   ),
      // },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
])
