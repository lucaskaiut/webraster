import { useCallback, useEffect, useRef, useState, type CSSProperties } from 'react'
import { createPortal } from 'react-dom'
import { AlertTriangle } from 'lucide-react'
import type { DeviceAlarm } from '@/shared/types/models'
import { cn } from '@/shared/utils/cn'

const TOOLTIP_MAX_WIDTH = 240
const TOOLTIP_GAP = 8

export function VehicleAlarmTooltip({
  alarms,
  iconClassName,
}: {
  alarms: DeviceAlarm[]
  iconClassName?: string
}) {
  const anchorRef = useRef<HTMLSpanElement>(null)
  const [open, setOpen] = useState(false)
  const [style, setStyle] = useState<CSSProperties>({})

  const updatePosition = useCallback(() => {
    const anchor = anchorRef.current

    if (!anchor) {
      return
    }

    const rect = anchor.getBoundingClientRect()
    const estimatedHeight = Math.max(alarms.length * 22 + 20, 48)
    const spaceAbove = rect.top
    const spaceBelow = window.innerHeight - rect.bottom
    const openUp = spaceAbove >= estimatedHeight || spaceAbove >= spaceBelow

    let left = rect.right + TOOLTIP_GAP
    let top = openUp ? rect.top - TOOLTIP_GAP : rect.bottom + TOOLTIP_GAP
    let transform = openUp ? 'translateY(-100%)' : undefined

    if (left + TOOLTIP_MAX_WIDTH > window.innerWidth - TOOLTIP_GAP) {
      left = rect.left - TOOLTIP_GAP
      transform = openUp ? 'translate(-100%, -100%)' : 'translateX(-100%)'
    }

    left = Math.max(TOOLTIP_GAP, Math.min(left, window.innerWidth - TOOLTIP_MAX_WIDTH - TOOLTIP_GAP))

    setStyle({
      position: 'fixed',
      left,
      top,
      transform,
      zIndex: 60,
      minWidth: '10rem',
      maxWidth: TOOLTIP_MAX_WIDTH,
    })
  }, [alarms.length])

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

  if (alarms.length === 0) {
    return null
  }

  return (
    <>
      <span
        ref={anchorRef}
        className="inline-flex shrink-0"
        onClick={(event) => event.stopPropagation()}
        onMouseDown={(event) => event.stopPropagation()}
        onMouseEnter={() => {
          updatePosition()
          setOpen(true)
        }}
        onMouseLeave={() => setOpen(false)}
        onFocus={() => {
          updatePosition()
          setOpen(true)
        }}
        onBlur={() => setOpen(false)}
      >
        <AlertTriangle
          aria-label={`${alarms.length} alarme${alarms.length === 1 ? '' : 's'} ativo${alarms.length === 1 ? '' : 's'}`}
          className={cn('size-3.5 text-warning', iconClassName)}
        />
      </span>

      {open &&
        createPortal(
          <div
            role="tooltip"
            style={style}
            className="pointer-events-none rounded-md bg-foreground px-3 py-2.5 text-background shadow-md"
          >
            <ul className="list-none space-y-1.5 text-xs leading-snug">
              {alarms.map((alarm) => (
                <li key={alarm.code} className="flex items-start gap-2">
                  <span
                    aria-hidden="true"
                    className="mt-1.5 size-1 shrink-0 rounded-full bg-background/70"
                  />
                  <span>{alarm.label}</span>
                </li>
              ))}
            </ul>
          </div>,
          document.body,
        )}
    </>
  )
}
