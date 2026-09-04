import { Suspense } from 'react'
import { Outlet } from 'react-router'
import { Zap } from 'lucide-react'
import { Loading, ThemeToggle } from '@/shared/design-system'

export function AuthLayout() {
  return (
    <div className="relative flex min-h-dvh flex-col items-center justify-center px-4 py-10">
      <div className="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div className="mb-8 flex items-center gap-2.5">
        <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-raised">
          <Zap className="size-5.5" aria-hidden="true" />
        </span>
        <span className="text-lg font-semibold tracking-tight text-foreground">Nox</span>
      </div>

      <Suspense fallback={<Loading />}>
        <Outlet />
      </Suspense>

      <p className="mt-8 text-xs text-subtle">
        © {new Date().getFullYear()} Nox — Painel administrativo
      </p>
    </div>
  )
}
