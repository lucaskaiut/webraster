import { cn } from '@/shared/utils/cn'
import { getInitials } from '@/shared/utils/format'

export function Avatar({
  name,
  size = 'md',
  className,
}: {
  name: string
  size?: 'sm' | 'md' | 'lg'
  className?: string
}) {
  const sizes = {
    sm: 'size-7 text-[11px]',
    md: 'size-9 text-xs',
    lg: 'size-12 text-sm',
  }

  return (
    <span
      aria-hidden="true"
      className={cn(
        'inline-flex shrink-0 items-center justify-center rounded-full bg-primary-soft font-semibold text-primary',
        sizes[size],
        className,
      )}
    >
      {getInitials(name)}
    </span>
  )
}
