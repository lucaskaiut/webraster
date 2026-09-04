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
const ClientsListPage = lazy(() => import('@/modules/clients/pages/ClientsListPage'))
const ClientCreatePage = lazy(() => import('@/modules/clients/pages/ClientCreatePage'))
const ClientEditPage = lazy(() => import('@/modules/clients/pages/ClientEditPage'))
const DriversListPage = lazy(() => import('@/modules/drivers/pages/DriversListPage'))
const DriverCreatePage = lazy(() => import('@/modules/drivers/pages/DriverCreatePage'))
const DriverEditPage = lazy(() => import('@/modules/drivers/pages/DriverEditPage'))

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
