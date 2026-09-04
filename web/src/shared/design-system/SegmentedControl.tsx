import { cn } from '@/shared/utils/cn'

export interface SegmentedOption<T extends string> {
  value: T
  label: string
}

export function SegmentedControl<T extends string>({
  value,
  options,
  onChange,
  className,
}: {
  value: T
  options: Array<SegmentedOption<T>>
  onChange: (value: T) => void
  className?: string
}) {
  const activeIndex = Math.max(
    options.findIndex((option) => option.value === value),
    0,
  )

  return (
    <div className={cn('relative flex w-full rounded-xl bg-surface-2 p-1', className)}>
      <div
        aria-hidden="true"
        className="absolute top-1 bottom-1 left-1 rounded-lg bg-surface shadow-raised transition-transform duration-300 ease-out"
        style={{
          width: `calc((100% - 0.5rem) / ${options.length})`,
          transform: `translateX(${activeIndex * 100}%)`,
        }}
      />
      {options.map((option) => (
        <button
          key={option.value}
          type="button"
          onClick={() => onChange(option.value)}
          className={cn(
            'relative z-10 flex-1 truncate rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200',
            option.value === value ? 'text-foreground' : 'text-muted hover:text-foreground',
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  )
}
