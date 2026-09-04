import type { ReactNode } from 'react'
import { cn } from '@/shared/utils/cn'

export function Topbar({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <header
      className={cn(
        'sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 bg-background px-4 sm:px-6 lg:px-8',
        className,
      )}
    >
      {children}
    </header>
  )
}
