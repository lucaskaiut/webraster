import { Suspense } from 'react'
import { Outlet } from 'react-router'
import loginBackground from '@/assets/login-background.webp'
import { Loading, ThemeToggle } from '@/shared/design-system'

export function AuthLayout() {
  return (
    <div className="relative flex h-dvh flex-col items-center justify-center overflow-y-auto overscroll-contain px-4 py-10">
      <div
        aria-hidden="true"
        className="pointer-events-none fixed inset-0 -z-10 bg-center bg-no-repeat opacity-20 dark:opacity-15"
        style={{ backgroundImage: `url(${loginBackground})`, backgroundSize: '70%' }}
      />

      <div className="absolute top-4 right-4">
        <ThemeToggle />
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
