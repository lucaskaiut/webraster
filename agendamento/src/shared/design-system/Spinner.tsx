import { cn } from '@/shared/utils/cn'

export function Spinner({ className }: { className?: string }) {
  return (
    <svg
      className={cn('size-5 animate-spin text-current', className)}
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle className="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path
        className="opacity-90"
        fill="currentColor"
        d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"
      />
    </svg>
  )
}

export function Loading({ label = 'Carregando...' }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-muted" role="status">
      <Spinner className="size-5" />
      <span className="text-sm">{label}</span>
    </div>
  )
}

export function FullScreenLoading() {
  return (
    <div className="flex h-dvh items-center justify-center bg-background" role="status">
      <Spinner className="size-7 text-primary" />
      <span className="sr-only">Carregando aplicação</span>
    </div>
  )
}
