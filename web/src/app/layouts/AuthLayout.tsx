import { Suspense } from 'react'
import { Outlet } from 'react-router'
import { AppLogo } from '@/shared/brand/AppLogo'
import { Loading, ThemeToggle } from '@/shared/design-system'

export function AuthLayout() {
  return (
    <div className="relative flex min-h-dvh flex-col items-center justify-center px-4 py-10">
      <div className="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div className="mb-8">
        <AppLogo size="lg" />
      </div>

      <Suspense fallback={<Loading />}>
        <Outlet />
      </Suspense>

      <p className="mt-8 text-xs text-subtle">
        © {new Date().getFullYear()} Web Raster — Painel administrativo
      </p>
    </div>
  )
}
