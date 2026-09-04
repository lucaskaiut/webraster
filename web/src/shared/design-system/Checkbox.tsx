import type { ComponentProps, ReactNode } from 'react'
import { Check } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

export interface CheckboxProps extends Omit<ComponentProps<'input'>, 'type' | 'size'> {
  label?: ReactNode
  description?: string
}

export function Checkbox({ label, description, className, id, ...props }: CheckboxProps) {
  const checkboxId = id ?? `checkbox-${props.name ?? ''}-${String(props.value ?? '')}`

  return (
    <label
      htmlFor={checkboxId}
      className={cn(
        'group flex cursor-pointer items-start gap-3 select-none',
        props.disabled && 'cursor-not-allowed opacity-60',
        className,
      )}
    >
      <span className="relative mt-0.5 inline-flex">
        <input id={checkboxId} type="checkbox" className="peer sr-only" {...props} />
        <span
          aria-hidden="true"
          className={cn(
            'flex size-4.5 items-center justify-center rounded-[6px] bg-surface-3 transition-colors',
            'peer-checked:bg-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring',
          )}
        >
          <Check className="size-3 text-white opacity-0 transition-opacity peer-checked:opacity-100 group-has-[input:checked]:opacity-100" />
        </span>
      </span>
      {(label || description) && (
        <span className="min-w-0">
          {label && <span className="block text-sm text-foreground">{label}</span>}
          {description && <span className="block text-[13px] text-muted">{description}</span>}
        </span>
      )}
    </label>
  )
}
