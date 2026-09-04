import type { ComponentProps } from 'react'
import { cn } from '@/shared/utils/cn'

export interface InputProps extends ComponentProps<'input'> {
  invalid?: boolean
}

export const inputClasses = (invalid?: boolean, className?: string) =>
  cn(
    'h-10 w-full rounded-lg bg-surface-2 px-3.5 text-sm text-foreground transition-colors',
    'placeholder:text-subtle',
    'disabled:cursor-not-allowed disabled:opacity-60',
    invalid && 'outline-2 outline-danger/60',
    className,
  )

export function Input({ invalid, className, ...props }: InputProps) {
  return <input aria-invalid={invalid || undefined} className={inputClasses(invalid, className)} {...props} />
}
