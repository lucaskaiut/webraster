import { useEffect, useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react'
import { cn } from '@/shared/utils/cn'
import type { ExternalApiSeries, Granularity } from '../types'
import { formatBucketLabel, formatBucketTick, NUMBER } from '../utils/period'
import { seriesColor } from '../utils/colors'

const HEIGHT = 280
const MARGIN = { top: 12, right: 16, bottom: 28, left: 48 }

function niceStep(max: number, targetTicks: number): number {
  if (max <= 0) return 1
  const raw = max / targetTicks
  const power = 10 ** Math.floor(Math.log10(raw))
  const normalized = raw / power
  const nice = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10

  return Math.max(1, nice * power)
}

interface RequestsLineChartProps {
  buckets: string[]
  series: ExternalApiSeries[]
  granularity: Granularity
  /** Slot de cor de cada serviço. */
  colorSlots: Record<string, number>
}

export function RequestsLineChart({ buckets, series, granularity, colorSlots }: RequestsLineChartProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)
  const [hidden, setHidden] = useState<Set<string>>(new Set())
  const [hoverIndex, setHoverIndex] = useState<number | null>(null)

  useEffect(() => {
    const element = containerRef.current
    if (!element) return

    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width))
    observer.observe(element)

    return () => observer.disconnect()
  }, [])

  const visible = series.filter((item) => !hidden.has(item.service))
  const innerWidth = Math.max(0, width - MARGIN.left - MARGIN.right)
  const innerHeight = HEIGHT - MARGIN.top - MARGIN.bottom

  const { yMax, yTicks } = useMemo(() => {
    const max = Math.max(0, ...visible.flatMap((item) => item.data))
    const step = niceStep(max, 4)
    const top = Math.max(step, Math.ceil(max / step) * step)
    const ticks: number[] = []
    for (let value = 0; value <= top; value += step) ticks.push(value)

    return { yMax: top, yTicks: ticks }
  }, [visible])

  const x = (index: number) =>
    buckets.length <= 1 ? innerWidth / 2 : (index / (buckets.length - 1)) * innerWidth
  const y = (value: number) => innerHeight - (value / yMax) * innerHeight

  const spansDays = useMemo(() => {
    if (buckets.length < 2) return false
    const first = new Date(buckets[0])
    const last = new Date(buckets[buckets.length - 1])

    return first.toDateString() !== last.toDateString()
  }, [buckets])

  const xTicks = useMemo(() => {
    if (buckets.length === 0 || innerWidth === 0) return []
    const labelWidth = spansDays && (granularity === 'minute' || granularity === 'hour') ? 120 : 64
    const maxTicks = Math.max(2, Math.floor(innerWidth / labelWidth))
    const every = Math.max(1, Math.ceil(buckets.length / maxTicks))
    const ticks: number[] = []
    for (let index = 0; index < buckets.length; index += every) ticks.push(index)

    return ticks
  }, [buckets, innerWidth, spansDays, granularity])

  const toggle = (service: string) =>
    setHidden((current) => {
      const next = new Set(current)
      if (next.has(service)) next.delete(service)
      else next.add(service)
      return next
    })

  const handlePointer = (event: PointerEvent<SVGRectElement>) => {
    if (buckets.length === 0) return
    const rect = event.currentTarget.getBoundingClientRect()
    const relative = event.clientX - rect.left
    const index = buckets.length <= 1 ? 0 : Math.round((relative / rect.width) * (buckets.length - 1))
    setHoverIndex(Math.min(buckets.length - 1, Math.max(0, index)))
  }

  const handleKey = (event: KeyboardEvent<SVGRectElement>) => {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return
    event.preventDefault()
    setHoverIndex((current) => {
      const base = current ?? (event.key === 'ArrowLeft' ? buckets.length : -1)
      const next = base + (event.key === 'ArrowLeft' ? -1 : 1)
      return Math.min(buckets.length - 1, Math.max(0, next))
    })
  }

  const tooltipLeft = hoverIndex !== null ? MARGIN.left + x(hoverIndex) : 0
  const tooltipOnLeft = tooltipLeft > width / 2

  return (
    <div className="flex flex-col gap-3">
      {series.length > 0 && (
        <ul className="flex flex-wrap gap-x-4 gap-y-1.5" aria-label="Legenda">
          {series.map((item) => {
            const off = hidden.has(item.service)
            return (
              <li key={item.service}>
                <button
                  type="button"
                  onClick={() => toggle(item.service)}
                  aria-pressed={!off}
                  className={cn(
                    'flex items-center gap-2 rounded-md px-1.5 py-1 text-sm transition-opacity hover:bg-surface-2',
                    off && 'opacity-45',
                  )}
                  title={off ? 'Mostrar série' : 'Ocultar série'}
                >
                  <span
                    aria-hidden="true"
                    className="h-0.5 w-4 rounded-full"
                    style={{ backgroundColor: seriesColor(colorSlots[item.service] ?? 0) }}
                  />
                  <span className="text-muted">{item.label}</span>
                  <span className="font-semibold tabular-nums text-foreground">{NUMBER.format(item.total)}</span>
                </button>
              </li>
            )
          })}
        </ul>
      )}

      <div ref={containerRef} className="relative w-full" style={{ height: HEIGHT }}>
        {width > 0 && (
          <svg width={width} height={HEIGHT} role="img" aria-label="Requisições por intervalo de tempo" className="block">
            <g transform={`translate(${MARGIN.left},${MARGIN.top})`}>
              {yTicks.map((tick) => (
                <g key={tick} transform={`translate(0,${y(tick)})`}>
                  <line x2={innerWidth} stroke="var(--app-surface-3)" strokeWidth={1} />
                  <text x={-8} dy="0.32em" textAnchor="end" className="fill-subtle text-[11px] tabular-nums">
                    {NUMBER.format(tick)}
                  </text>
                </g>
              ))}

              {xTicks.map((index) => (
                <text
                  key={index}
                  x={x(index)}
                  y={innerHeight + 18}
                  textAnchor={buckets.length > 1 && index === 0 ? 'start' : 'middle'}
                  className="fill-subtle text-[11px] tabular-nums"
                >
                  {formatBucketTick(buckets[index], granularity, spansDays)}
                </text>
              ))}

              {visible.map((item) => {
                const points = item.data.map((value, index) => `${x(index)},${y(value)}`).join(' ')
                return (
                  <polyline
                    key={item.service}
                    points={points}
                    fill="none"
                    stroke={seriesColor(colorSlots[item.service] ?? 0)}
                    strokeWidth={2}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                  />
                )
              })}

              {hoverIndex !== null && (
                <g pointerEvents="none">
                  <line
                    x1={x(hoverIndex)}
                    x2={x(hoverIndex)}
                    y2={innerHeight}
                    stroke="var(--app-fg-subtle)"
                    strokeWidth={1}
                  />
                  {visible.map((item) => (
                    <circle
                      key={item.service}
                      cx={x(hoverIndex)}
                      cy={y(item.data[hoverIndex] ?? 0)}
                      r={4}
                      fill={seriesColor(colorSlots[item.service] ?? 0)}
                      stroke="var(--app-surface)"
                      strokeWidth={2}
                    />
                  ))}
                </g>
              )}

              <rect
                width={innerWidth}
                height={innerHeight}
                fill="transparent"
                tabIndex={0}
                aria-label="Use as setas para navegar pelos intervalos"
                className="outline-none focus-visible:stroke-[var(--app-ring)]"
                onPointerMove={handlePointer}
                onPointerDown={handlePointer}
                onPointerLeave={() => setHoverIndex(null)}
                onKeyDown={handleKey}
                onBlur={() => setHoverIndex(null)}
              />
            </g>
          </svg>
        )}

        {hoverIndex !== null && (
          <div
            role="status"
            className="pointer-events-none absolute top-2 z-10 min-w-48 rounded-lg bg-surface px-3 py-2 text-sm shadow-pop"
            style={
              tooltipOnLeft
                ? { right: width - tooltipLeft + 12 }
                : { left: tooltipLeft + 12 }
            }
          >
            <p className="mb-1.5 text-xs text-muted">{formatBucketLabel(buckets[hoverIndex], granularity)}</p>
            <ul className="flex flex-col gap-1">
              {visible.map((item) => {
                const errors = item.errors[hoverIndex] ?? 0
                return (
                  <li key={item.service} className="flex items-center gap-2">
                    <span
                      aria-hidden="true"
                      className="size-2 shrink-0 rounded-full"
                      style={{ backgroundColor: seriesColor(colorSlots[item.service] ?? 0) }}
                    />
                    <span className="font-semibold tabular-nums text-foreground">
                      {NUMBER.format(item.data[hoverIndex] ?? 0)}
                    </span>
                    <span className="truncate text-muted">{item.label}</span>
                    {errors > 0 && (
                      <span className="ml-auto text-xs text-danger">{NUMBER.format(errors)} erro(s)</span>
                    )}
                  </li>
                )
              })}
            </ul>
          </div>
        )}
      </div>
    </div>
  )
}
