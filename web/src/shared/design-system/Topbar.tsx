import type { ReactNode } from 'react'
import { cn } from '@/shared/utils/cn'

export function Topbar({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <header
      className={cn(
        'z-30 flex h-12 shrink-0 items-center gap-2 bg-background px-3 sm:px-4 lg:px-5',
        className,
      )}
    >
      {children}
    </header>
  )
}
