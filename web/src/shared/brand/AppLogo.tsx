import { cn } from '@/shared/utils/cn'

const sizes = {
  sm: 'h-9',
  md: 'h-12',
  lg: 'h-14',
} as const

export function AppLogo({
  size = 'md',
  className,
}: {
  size?: keyof typeof sizes
  className?: string
}) {
  return (
    <img
      src="/logo.png"
      alt="Web Raster Rastreadores"
      className={cn('w-auto max-w-full rounded-lg object-contain object-left', sizes[size], className)}
    />
  )
}
