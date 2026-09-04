import { cn } from '@/shared/utils/cn'

export interface RadioOption<T extends string = string> {
  value: T
  label: string
  description?: string
}

export interface RadioGroupProps<T extends string = string> {
  name: string
  value: T | null
  onChange: (value: T) => void
  options: Array<RadioOption<T>>
  disabled?: boolean
  className?: string
  'aria-label'?: string
}

export function RadioGroup<T extends string = string>({
  name,
  value,
  onChange,
  options,
  disabled,
  className,
  ...props
}: RadioGroupProps<T>) {
  return (
    <div role="radiogroup" aria-label={props['aria-label']} className={cn('space-y-2', className)}>
      {options.map((option) => {
        const id = `${name}-${option.value}`
        const selected = value === option.value

        return (
          <label
            key={option.value}
            htmlFor={id}
            className={cn(
              'flex cursor-pointer items-start gap-3 rounded-lg p-3 transition-colors select-none',
              selected ? 'bg-primary-soft' : 'bg-surface-2 hover:bg-surface-3',
              disabled && 'cursor-not-allowed opacity-60',
            )}
          >
            <span className="relative mt-0.5 inline-flex">
              <input
                id={id}
                type="radio"
                name={name}
                value={option.value}
                checked={selected}
                disabled={disabled}
                onChange={() => onChange(option.value)}
                className="peer sr-only"
              />
              <span
                aria-hidden="true"
                className={cn(
                  'flex size-4.5 items-center justify-center rounded-full transition-colors',
                  selected ? 'bg-primary' : 'bg-surface-3',
                  'peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring',
                )}
              >
                {selected && <span className="size-1.5 rounded-full bg-white" />}
              </span>
            </span>
            <span className="min-w-0">
              <span className={cn('block text-sm font-medium', selected ? 'text-primary' : 'text-foreground')}>
                {option.label}
              </span>
              {option.description && (
                <span className="block text-[13px] text-muted">{option.description}</span>
              )}
            </span>
          </label>
        )
      })}
    </div>
  )
}
