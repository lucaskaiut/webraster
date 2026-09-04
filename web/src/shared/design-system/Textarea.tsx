import type { ComponentProps } from 'react'
import { cn } from '@/shared/utils/cn'

export interface TextareaProps extends ComponentProps<'textarea'> {
  invalid?: boolean
}

export function Textarea({ invalid, className, rows = 4, ...props }: TextareaProps) {
  return (
    <textarea
      rows={rows}
      aria-invalid={invalid || undefined}
      className={cn(
        'w-full rounded-lg bg-surface-2 px-3.5 py-2.5 text-sm text-foreground transition-colors',
        'placeholder:text-subtle',
        'disabled:cursor-not-allowed disabled:opacity-60',
        invalid && 'outline-2 outline-danger/60',
        className,
      )}
      {...props}
    />
  )
}
