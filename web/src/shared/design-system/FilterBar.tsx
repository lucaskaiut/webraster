import type { ComponentProps, ReactNode } from 'react'
import { Search } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { inputClasses } from './Input'

export function FilterBar({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div className={cn('flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between', className)}>
      {children}
    </div>
  )
}

export function SearchInput({ className, ...props }: ComponentProps<'input'>) {
  return (
    <div className={cn('relative w-full sm:max-w-xs', className)}>
      <Search
        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"
        aria-hidden="true"
      />
      <input type="search" className={cn(inputClasses(false), 'pl-9')} {...props} />
    </div>
  )
}
