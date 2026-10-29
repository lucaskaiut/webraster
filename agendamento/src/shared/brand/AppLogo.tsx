import { cn } from '@/shared/utils/cn'

export const DEFAULT_LOGO = '/logo.png'

const sizes = {
  sm: 'h-9',
  md: 'h-12',
  lg: 'h-14',
} as const

export function AppLogo({
  size = 'md',
  className,
  alt = 'Web Raster Rastreadores',
}: {
  size?: keyof typeof sizes
  className?: string
  alt?: string
}) {
  return (
    <img
      src={DEFAULT_LOGO}
      alt={alt}
      className={cn('w-auto max-w-full rounded-lg object-contain object-left', sizes[size], className)}
    />
  )
}
