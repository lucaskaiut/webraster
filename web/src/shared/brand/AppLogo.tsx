import { useEffect, useState } from 'react'
import { useActiveTenant } from './useActiveTenant'
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
  const tenant = useActiveTenant()
  const tenantLogo = tenant?.logo_url ?? null
  const [src, setSrc] = useState(tenantLogo ?? DEFAULT_LOGO)

  useEffect(() => {
    setSrc(tenantLogo ?? DEFAULT_LOGO)
  }, [tenantLogo])

  return (
    <img
      src={src}
      alt={alt}
      onError={() => {
        if (src !== DEFAULT_LOGO) {
          setSrc(DEFAULT_LOGO)
        }
      }}
      className={cn('w-auto max-w-full rounded-lg object-contain object-left', sizes[size], className)}
    />
  )
}
