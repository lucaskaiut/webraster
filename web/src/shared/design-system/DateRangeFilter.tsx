import {
  useEffect,
  useId,
  useRef,
  useState,
  type KeyboardEvent,
} from 'react'
import { createPortal } from 'react-dom'
import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import type { DateRange } from '@/shared/utils/date'
import {
  formatMonthYearLabel,
  formatRangeLabel,
  getVisiblePresets,
  isRangeEmpty,
  matchPreset,
  navigateRange,
  resolvePresetRange,
  type DateRangePreset,
} from '@/shared/utils/date-range'
import { RangeCalendar } from './RangeCalendar'

export type { DateRange } from '@/shared/utils/date'

export interface DateRangeFilterProps {
  from: string
  to: string
  onChange: (range: DateRange) => void
  variant?: 'range' | 'single'
  label?: string
  showClear?: boolean
  className?: string
  min?: string
  max?: string
}

export function DateRangeFilter({
  from,
  to,
  onChange,
  variant = 'range',
  label,
  showClear = false,
  className,
  min,
  max,
}: DateRangeFilterProps) {
  const triggerId = useId()
  const popoverId = useId()
  const containerRef = useRef<HTMLDivElement>(null)
  const popoverRef = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [panel, setPanel] = useState<'presets' | 'custom'>('presets')
  const [customStart, setCustomStart] = useState<string | null>(null)
  const [customEnd, setCustomEnd] = useState<string | null>(null)

  const hasValue = !isRangeEmpty(from, to)
  const activePreset = hasValue ? matchPreset(from, to) : showClear ? 'all_time' : 'custom'
  const displayLabel = formatMonthYearLabel(from, to) ?? formatRangeLabel(from, to)
  const canNavigate = hasValue
  const presets = getVisiblePresets({ variant, allowAllTime: showClear })

  const applyRange = (range: DateRange) => {
    onChange(range)
    setOpen(false)
    setPanel('presets')
    setCustomStart(null)
    setCustomEnd(null)
  }

  const applyPreset = (preset: DateRangePreset) => {
    if (preset === 'custom') {
      setPanel('custom')
      setCustomStart(from || null)
      setCustomEnd(to || null)
      return
    }

    applyRange(resolvePresetRange(preset))
  }

  const handleNavigate = (direction: -1 | 1) => {
    if (!canNavigate) {
      return
    }

    applyRange(navigateRange(from, to, direction))
  }

  const updatePosition = () => {
    const anchor = containerRef.current
    const popover = popoverRef.current

    if (!anchor || !popover) {
      return
    }

    const rect = anchor.getBoundingClientRect()
    const popoverWidth = panel === 'custom' ? 296 : 240
    const popoverHeight = panel === 'custom' ? 380 : 420
    const spaceBelow = window.innerHeight - rect.bottom
    const openUp = spaceBelow < popoverHeight && rect.top > popoverHeight

    popover.style.position = 'fixed'
    popover.style.left = `${Math.min(rect.left, window.innerWidth - popoverWidth - 16)}px`
    popover.style.top = openUp ? `${rect.top - 8}px` : `${rect.bottom + 8}px`
    popover.style.transform = openUp ? 'translateY(-100%)' : 'none'
    popover.style.zIndex = '60'
  }

  useEffect(() => {
    if (!open) {
      return
    }

    updatePosition()

    const onPointerDown = (event: PointerEvent) => {
      const target = event.target as Node

      if (containerRef.current?.contains(target) || popoverRef.current?.contains(target)) {
        return
      }

      setOpen(false)
      setPanel('presets')
    }

    const onKeyDown = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
        setPanel('presets')
      }
    }

    const onReposition = () => updatePosition()

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    window.addEventListener('resize', onReposition)
    window.addEventListener('scroll', onReposition, true)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
      window.removeEventListener('resize', onReposition)
      window.removeEventListener('scroll', onReposition, true)
    }
  }, [open, panel])

  useEffect(() => {
    if (open) {
      updatePosition()
    }
  }, [panel, open])

  const handleTriggerKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'ArrowLeft') {
      event.preventDefault()
      handleNavigate(-1)
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault()
      handleNavigate(1)
    }

    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      setOpen(true)
    }
  }

  return (
    <div className={cn('space-y-2', className)}>
      {label && <span className="text-[13px] font-medium text-muted">{label}</span>}

      <div ref={containerRef} className="flex items-center gap-1">
        <button
          type="button"
          aria-label="Período anterior"
          disabled={!canNavigate}
          onClick={() => handleNavigate(-1)}
          className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-surface-2 text-muted transition-colors hover:bg-surface-3 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
        >
          <ChevronLeft className="size-4" />
        </button>

        <button
          type="button"
          id={triggerId}
          aria-haspopup="dialog"
          aria-expanded={open}
          aria-controls={popoverId}
          onClick={() => {
            setOpen((current) => !current)
            setPanel('presets')
          }}
          onKeyDown={handleTriggerKeyDown}
          className={cn(
            'flex h-10 min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg bg-surface-2 px-3 text-sm transition-colors',
            'shadow-[inset_0_0_0_1px_var(--app-surface-3)] hover:shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--app-fg-subtle)_55%,transparent)]',
            open && 'shadow-[inset_0_0_0_2px_color-mix(in_srgb,var(--app-primary)_45%,transparent)]',
          )}
        >
          <CalendarDays className="size-4 shrink-0 text-subtle" aria-hidden="true" />
          <span className="min-w-0 flex-1 truncate text-left text-foreground">{displayLabel}</span>
          <ChevronDown className={cn('size-4 shrink-0 text-subtle transition-transform', open && 'rotate-180')} aria-hidden="true" />
        </button>

        <button
          type="button"
          aria-label="Próximo período"
          disabled={!canNavigate}
          onClick={() => handleNavigate(1)}
          className="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-surface-2 text-muted transition-colors hover:bg-surface-3 hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
        >
          <ChevronRight className="size-4" />
        </button>
      </div>

      {open &&
        createPortal(
          <div
            ref={popoverRef}
            id={popoverId}
            role="dialog"
            aria-labelledby={triggerId}
            className="animate-rise-in overflow-hidden rounded-2xl border border-surface-3 bg-surface shadow-pop"
          >
            {panel === 'presets' ? (
              <div className="max-h-[min(24rem,calc(100vh-6rem))] overflow-y-auto p-1.5">
                {presets.map((preset, index) => {
                  const showSeparator =
                    index > 0
                    && preset.group !== presets[index - 1]?.group
                    && preset.id !== 'all_time'

                  return (
                    <div key={preset.id}>
                      {showSeparator && <div className="my-1.5 h-px bg-surface-2" role="separator" />}
                      <button
                        type="button"
                        role="menuitemradio"
                        aria-checked={activePreset === preset.id}
                        onClick={() => applyPreset(preset.id)}
                        className={cn(
                          'flex w-full cursor-pointer items-center rounded-lg px-3 py-2 text-left text-sm transition-colors',
                          activePreset === preset.id
                            ? 'bg-primary-soft font-medium text-primary'
                            : 'text-foreground hover:bg-surface-2',
                        )}
                      >
                        {preset.label}
                      </button>
                    </div>
                  )
                })}

                <div className="my-1.5 h-px bg-surface-2" role="separator" />

                <button
                  type="button"
                  role="menuitemradio"
                  aria-checked={activePreset === 'custom'}
                  onClick={() => applyPreset('custom')}
                  className={cn(
                    'flex w-full cursor-pointer items-center rounded-lg px-3 py-2 text-left text-sm transition-colors',
                    activePreset === 'custom'
                      ? 'bg-primary-soft font-medium text-primary'
                      : 'text-foreground hover:bg-surface-2',
                  )}
                >
                  Período personalizado
                </button>
              </div>
            ) : (
              <div className="p-1">
                <div className="flex items-center justify-between border-b border-surface-2 px-3 py-2">
                  <button
                    type="button"
                    onClick={() => setPanel('presets')}
                    className="cursor-pointer text-[13px] font-medium text-primary transition-colors hover:text-primary-hover"
                  >
                    ← Voltar
                  </button>
                  <span className="text-[13px] font-medium text-muted">Período personalizado</span>
                </div>

                <RangeCalendar
                  startDate={customStart ?? undefined}
                  endDate={customEnd ?? undefined}
                  min={min}
                  max={max}
                  singleSelect={variant === 'single'}
                  onChange={({ startDate, endDate }) => {
                    setCustomStart(startDate)
                    setCustomEnd(endDate)
                  }}
                  onComplete={({ startDate, endDate }) => {
                    applyRange({ from: startDate, to: endDate })
                  }}
                />
              </div>
            )}
          </div>,
          document.body,
        )}
    </div>
  )
}
