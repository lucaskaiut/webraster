import type { ComponentProps } from 'react'
import { ChevronDown } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

export interface SelectOption {
  value: string
  label: string
}

export interface SelectProps extends ComponentProps<'select'> {
  invalid?: boolean
  options: SelectOption[]
  placeholder?: string
}

export function Select({ invalid, options, placeholder, className, ...props }: SelectProps) {
  return (
    <div className="relative">
      <select
        aria-invalid={invalid || undefined}
        className={cn(
          'h-10 w-full cursor-pointer appearance-none rounded-lg bg-surface-2 pr-9 pl-3.5 text-sm text-foreground transition-colors',
          'disabled:cursor-not-allowed disabled:opacity-60',
          invalid && 'outline-2 outline-danger/60',
          className,
        )}
        {...props}
      >
        {placeholder && <option value="">{placeholder}</option>}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <ChevronDown
        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-subtle"
        aria-hidden="true"
      />
    </div>
  )
}
