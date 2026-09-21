import { useCallback, useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react'
import { GripHorizontal, List, X } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { fleetStats } from '../lib/tracking'
import { FleetSidebar, type FleetSidebarProps } from './FleetSidebar'

const PANEL_WIDTH = 300
const PANEL_HEIGHT = 560
const EDGE_MARGIN = 12
const INITIAL_POSITION = { x: EDGE_MARGIN, y: 160 }

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max)
}

function isDesktop(): boolean {
  return typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches
}

export function FleetPanel(props: FleetSidebarProps) {
  const [open, setOpen] = useState(isDesktop)
  const [position, setPosition] = useState(INITIAL_POSITION)
  const panelRef = useRef<HTMLDivElement>(null)
  const dragRef = useRef<{ pointerId: number; offsetX: number; offsetY: number } | null>(null)
  const stats = fleetStats(props.vehicles)

  const getContainer = useCallback(
    () => (panelRef.current?.offsetParent as HTMLElement | null) ?? null,
    [],
  )

  const clampPosition = useCallback(() => {
    const panel = panelRef.current
    const container = getContainer()
    if (!panel || !container) return

    setPosition((current) => ({
      x: clamp(current.x, 0, Math.max(0, container.clientWidth - panel.offsetWidth)),
      y: clamp(current.y, 0, Math.max(0, container.clientHeight - panel.offsetHeight)),
    }))
  }, [getContainer])

  useEffect(() => {
    if (!open) return

    clampPosition()

    const container = getContainer()
    if (!container || typeof ResizeObserver === 'undefined') return

    const observer = new ResizeObserver(clampPosition)
    observer.observe(container)

    return () => observer.disconnect()
  }, [open, clampPosition, getContainer])

  const handleDragStart = (event: ReactPointerEvent<HTMLDivElement>) => {
    if ((event.target as HTMLElement).closest('button, a, input, select, label')) return

    const panel = panelRef.current
    if (!panel) return

    const rect = panel.getBoundingClientRect()
    dragRef.current = {
      pointerId: event.pointerId,
      offsetX: event.clientX - rect.left,
      offsetY: event.clientY - rect.top,
    }

    event.currentTarget.setPointerCapture(event.pointerId)
  }

  const handleDragMove = (event: ReactPointerEvent<HTMLDivElement>) => {
    const drag = dragRef.current
    const panel = panelRef.current
    const container = getContainer()
    if (!drag || drag.pointerId !== event.pointerId || !panel || !container) return

    const rect = container.getBoundingClientRect()
    const maxX = Math.max(0, container.clientWidth - panel.offsetWidth)
    const maxY = Math.max(0, container.clientHeight - panel.offsetHeight)

    setPosition({
      x: clamp(event.clientX - rect.left - drag.offsetX, 0, maxX),
      y: clamp(event.clientY - rect.top - drag.offsetY, 0, maxY),
    })
  }

  const handleDragEnd = () => {
    dragRef.current = null
  }

  const handleSelect = (id: string) => {
    props.onSelect(id)
    if (!isDesktop()) setOpen(false)
  }

  if (!open) {
    return (
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="Mostrar lista de veículos"
        className="animate-fade-in absolute top-1/2 left-3 z-20 flex -translate-y-1/2 items-center gap-2 rounded-lg bg-surface px-2.5 py-2 text-sm font-medium text-foreground shadow-pop transition-colors hover:bg-surface-2"
      >
        <List className="size-4" aria-hidden="true" />
        <span className="hidden sm:inline">Veículos</span>
        <span className="rounded-full bg-surface-2 px-1.5 text-xs text-muted">{stats.total}</span>
      </button>
    )
  }

  return (
    <div
      ref={panelRef}
      role="region"
      aria-label="Lista de veículos"
      style={{
        left: position.x,
        top: position.y,
        width: PANEL_WIDTH,
        maxWidth: `calc(100% - ${EDGE_MARGIN * 2}px)`,
        height: `min(${PANEL_HEIGHT}px, calc(100% - ${EDGE_MARGIN * 2}px))`,
      }}
      className="animate-fade-in absolute z-20 flex flex-col overflow-hidden rounded-xl bg-surface shadow-pop"
    >
      <div
        onPointerDown={handleDragStart}
        onPointerMove={handleDragMove}
        onPointerUp={handleDragEnd}
        onPointerCancel={handleDragEnd}
        className={cn(
          'flex items-center gap-2 px-2.5 py-2 select-none',
          'cursor-grab touch-none active:cursor-grabbing',
        )}
      >
        <GripHorizontal className="size-4 text-subtle" aria-hidden="true" />
        <span className="text-sm font-medium text-foreground">Veículos</span>
        <span className="text-xs text-muted">{stats.total}</span>
        <button
          type="button"
          onClick={() => setOpen(false)}
          aria-label="Esconder lista de veículos"
          className="ml-auto flex size-7 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
        >
          <X className="size-4" />
        </button>
      </div>

      <FleetSidebar
        {...props}
        onSelect={handleSelect}
        className="min-h-0 flex-1 rounded-none bg-transparent shadow-none"
      />
    </div>
  )
}
