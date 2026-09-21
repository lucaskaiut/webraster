import { useCallback, useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { cn } from '@/shared/utils/cn'

const GAP = 8
const DEFAULT_MAX_WIDTH = 260

export function Tooltip({
  content,
  children,
  maxWidth = DEFAULT_MAX_WIDTH,
  className,
}: {
  content: ReactNode
  children: ReactNode
  maxWidth?: number
  className?: string
}) {
  const anchorRef = useRef<HTMLSpanElement>(null)
  const tooltipRef = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [style, setStyle] = useState<CSSProperties>({
    position: 'fixed',
    left: -9999,
    top: -9999,
  })

  const updatePosition = useCallback(() => {
    const anchor = anchorRef.current

    if (!anchor) {
      return
    }

    const rect = anchor.getBoundingClientRect()
    const width = Math.min(maxWidth, window.innerWidth - GAP * 2)
    const height = tooltipRef.current?.offsetHeight ?? 48
    const openUp = rect.top >= height + GAP || rect.top >= window.innerHeight - rect.bottom

    let left = rect.left + rect.width / 2 - width / 2
    left = Math.max(GAP, Math.min(left, window.innerWidth - width - GAP))

    let top = openUp ? rect.top - height - GAP : rect.bottom + GAP
    top = Math.max(GAP, Math.min(top, window.innerHeight - height - GAP))

    setStyle({ position: 'fixed', left, top, width, zIndex: 60 })
  }, [maxWidth])

  useEffect(() => {
    if (!open) {
      return
    }

    updatePosition()

    window.addEventListener('scroll', updatePosition, true)
    window.addEventListener('resize', updatePosition)

    return () => {
      window.removeEventListener('scroll', updatePosition, true)
      window.removeEventListener('resize', updatePosition)
    }
  }, [open, updatePosition])

  return (
    <>
      <span
        ref={anchorRef}
        className="inline-flex shrink-0"
        onMouseEnter={() => setOpen(true)}
        onMouseLeave={() => setOpen(false)}
      >
        {children}
      </span>

      {open &&
        createPortal(
          <div
            ref={tooltipRef}
            role="tooltip"
            style={style}
            className={cn(
              'pointer-events-none rounded-md bg-foreground px-3 py-2.5 text-background shadow-md',
              className,
            )}
          >
            {content}
          </div>,
          document.body,
        )}
    </>
  )
}
