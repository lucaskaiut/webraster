import type { ComponentProps } from 'react'
import { Loader2 } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

export interface InputProps extends ComponentProps<'input'> {
  invalid?: boolean
  loading?: boolean
  suffix?: string
}

export const inputClasses = (invalid?: boolean, className?: string) =>
  cn(
    'h-10 w-full rounded-lg bg-surface-2 px-3.5 text-sm text-foreground transition-colors',
    'placeholder:text-subtle',
    'disabled:cursor-not-allowed disabled:opacity-60',
    invalid && 'outline-2 outline-danger/60',
    className,
  )

export function Input({ invalid, loading, className, suffix, ...props }: InputProps) {
  if (!loading && !suffix) {
    return <input aria-invalid={invalid || undefined} className={inputClasses(invalid, className)} {...props} />
  }

  const padding = loading ? 'pr-10' : 'pr-16'

  return (
    <div className="relative">
      <input
        aria-invalid={invalid || undefined}
        className={cn(inputClasses(invalid, className), padding)}
        {...props}
      />
      {loading ? (
        <Loader2
          className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 animate-spin text-subtle"
          aria-hidden="true"
        />
      ) : (
        suffix && (
          <span className="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-xs text-muted">
            {suffix}
          </span>
        )
      )}
    </div>
  )
}
