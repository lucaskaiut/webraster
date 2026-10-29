import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { AuthGuard } from '@/app/guards/AuthGuard'
import { GuestGuard } from '@/app/guards/GuestGuard'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AppLayout } from '@/app/layouts/AppLayout'
import { AuthLayout } from '@/app/layouts/AuthLayout'
import { Permission } from '@/shared/constants/permissions'
import { HOME_PATH } from '@/shared/constants/routes'
import { NotFoundPage } from './NotFoundPage'

const LoginPage = lazy(() => import('@/modules/auth/pages/LoginPage'))
const ForgotPasswordPage = lazy(() => import('@/modules/auth/pages/ForgotPasswordPage'))
const ResetPasswordPage = lazy(() => import('@/modules/auth/pages/ResetPasswordPage'))
const ServiceOrdersListPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrdersListPage'))
const ServiceOrderCreatePage = lazy(() => import('@/modules/service-orders/pages/ServiceOrderCreatePage'))
const ServiceOrderDetailPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrderDetailPage'))
const ServiceOrdersKanbanPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrdersKanbanPage'))
const ServiceOrdersCalendarPage = lazy(() => import('@/modules/service-orders/pages/ServiceOrdersCalendarPage'))

export const router = createBrowserRouter([
  {
    element: <GuestGuard />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          { path: '/auth/login', element: <LoginPage /> },
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
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to={HOME_PATH} replace /> },
          { path: '/dashboard', element: <Navigate to={HOME_PATH} replace /> },
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
        ],
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
])
