import { useCallback, useEffect, useRef, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button, Spinner } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import {
  formatDayCard,
  formatFullDay,
  formatTime,
  sameInstant,
  slotPeriod,
  type ScheduleDayView,
  type ScheduleSlotView,
} from '../lib/schedule'
import { ServiceOrderDayAvailabilityModal } from './ServiceOrderDayAvailabilityModal'

const CARD_GAP_PX = 16
const PRELOAD_MARGIN_PX = 320

interface ServiceOrderAvailabilityBoardProps {
  days: ScheduleDayView[]
  selectedDate: string | null
  selectedStart: string | null
  loading: boolean
  loadingMore: boolean
  hasMore: boolean
  error: string | null
  failedCount: number
  searchedUntil: string | null
  confirmingStart: string | null
  disabled: boolean
  selectionWarning: string | null
  onReload: () => void
  onLoadMore: () => void
  onRetryFailed: () => void
  onClearWarning: () => void
  onSelectSlot: (date: string, slot: ScheduleSlotView) => Promise<boolean>
}

function DayCard({
  day,
  selectedDate,
  selectedStart,
  confirmingStart,
  disabled,
  onOpenMore,
  onSelect,
}: {
  day: ScheduleDayView
  selectedDate: string | null
  selectedStart: string | null
  confirmingStart: string | null
  disabled: boolean
  onOpenMore: () => void
  onSelect: (slot: ScheduleSlotView) => void
}) {
  const { weekday, shortDate } = formatDayCard(day.date)
  const selectedSlot =
    selectedDate === day.date && selectedStart
      ? day.availableSlots.find((slot) => sameInstant(slot.start, selectedStart))
      : undefined
  const selectedIsSuggestion = selectedSlot
    ? day.suggestedSlots.some((slot) => sameInstant(slot.start, selectedSlot.start))
    : false

  return (
    <li data-schedule-card className="w-52 shrink-0 snap-start sm:w-60">
      <div className="flex h-full min-h-44 flex-col rounded-xl bg-surface p-4 shadow-card">
        <div className="flex items-baseline justify-between gap-3">
          <span className="text-xs font-semibold tracking-wide text-foreground uppercase">
            {weekday}
          </span>
          <span className="text-xs tabular-nums text-muted">{shortDate}</span>
        </div>

        <div className="mt-4 space-y-2">
          {day.suggestedSlots.map((slot) => {
            const selected = Boolean(selectedStart && sameInstant(slot.start, selectedStart))
            const confirming = Boolean(
              confirmingStart && sameInstant(slot.start, confirmingStart),
            )
            const period = slotPeriod(slot.start, day.timezone)
            const periodLabel = period === 'morning' ? 'Manhã' : 'Tarde'
            const time = formatTime(slot.start, day.timezone)

            return (
              <button
                key={slot.start}
                type="button"
                className={cn(
                  'flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left transition-colors disabled:pointer-events-none disabled:opacity-60',
                  selected
                    ? 'bg-primary text-primary-foreground shadow-card hover:bg-primary-hover'
                    : 'bg-surface-2 text-foreground hover:bg-surface-3',
                )}
                aria-pressed={selected}
                aria-label={`${selected ? 'Horário selecionado' : 'Selecionar'} ${periodLabel.toLowerCase()} de ${weekday}, ${shortDate}, às ${time}`}
                disabled={disabled}
                onClick={() => onSelect(slot)}>
                <span
                  className={cn(
                    'text-[11px] font-medium tracking-wide uppercase',
                    selected ? 'text-primary-foreground/75' : 'text-muted',
                  )}>
                  {periodLabel}
                </span>
                <span className="flex items-center gap-2 text-[15px] font-semibold tabular-nums">
                  {confirming && <Spinner className="size-3.5" />}
                  {time}
                </span>
              </button>
            )
          })}
        </div>

        {selectedSlot && !selectedIsSuggestion && (
          <p className="mt-3 rounded-lg bg-primary-soft px-3 py-2 text-xs font-medium text-primary">
            Selecionado · {formatTime(selectedSlot.start, day.timezone)}
          </p>
        )}

        {day.hasMoreSlots && (
          <button
            type="button"
            className="mt-auto pt-3 text-left text-xs font-medium text-primary transition-colors hover:text-primary-hover"
            aria-haspopup="dialog"
            aria-label={`Ver todos os horários de ${weekday}, ${shortDate}`}
            onClick={onOpenMore}>
            Ver mais horários
          </button>
        )}
      </div>
    </li>
  )
}

export function ServiceOrderAvailabilityBoard({
  days,
  selectedDate,
  selectedStart,
  loading,
  loadingMore,
  hasMore,
  error,
  failedCount,
  searchedUntil,
  confirmingStart,
  disabled,
  selectionWarning,
  onReload,
  onLoadMore,
  onRetryFailed,
  onClearWarning,
  onSelectSlot,
}: ServiceOrderAvailabilityBoardProps) {
  const scrollerRef = useRef<HTMLDivElement>(null)
  const sentinelRef = useRef<HTMLLIElement>(null)
  const [modalDate, setModalDate] = useState<string | null>(null)
  const [canScrollPrev, setCanScrollPrev] = useState(false)
  const [canScrollNext, setCanScrollNext] = useState(false)

  const updateScrollState = useCallback(() => {
    const scroller = scrollerRef.current

    if (!scroller) return

    setCanScrollPrev(scroller.scrollLeft > 2)
    setCanScrollNext(scroller.scrollLeft + scroller.clientWidth < scroller.scrollWidth - 2)
  }, [])

  useEffect(() => {
    updateScrollState()

    const scroller = scrollerRef.current

    if (!scroller || typeof ResizeObserver === 'undefined') return

    const observer = new ResizeObserver(updateScrollState)
    observer.observe(scroller)

    return () => observer.disconnect()
  }, [updateScrollState])

  useEffect(() => {
    const frame = requestAnimationFrame(updateScrollState)

    return () => cancelAnimationFrame(frame)
  }, [days.length, loadingMore, hasMore, updateScrollState])

  // Fecha a modal se o dia deixou de ter horários após uma atualização.
  useEffect(() => {
    if (modalDate && !days.some((day) => day.date === modalDate)) {
      setModalDate(null)
    }
  }, [days, modalDate])

  useEffect(() => {
    const root = scrollerRef.current
    const target = sentinelRef.current

    if (
      !hasMore ||
      loading ||
      loadingMore ||
      error ||
      !root ||
      !target ||
      typeof IntersectionObserver === 'undefined'
    ) {
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) onLoadMore()
      },
      { root, rootMargin: `0px ${PRELOAD_MARGIN_PX}px 0px 0px`, threshold: 0.01 },
    )

    observer.observe(target)

    return () => observer.disconnect()
  }, [error, hasMore, loading, loadingMore, onLoadMore])

  // Preenche a área visível mesmo quando o lote retornou poucos dias.
  useEffect(() => {
    if (!hasMore || loading || loadingMore || error) return

    const root = scrollerRef.current
    const sentinel = sentinelRef.current

    if (!root || !sentinel) return

    const rootRect = root.getBoundingClientRect()
    const sentinelRect = sentinel.getBoundingClientRect()

    if (sentinelRect.left <= rootRect.right + PRELOAD_MARGIN_PX) onLoadMore()
  }, [days.length, error, hasMore, loading, loadingMore, onLoadMore])

  const scrollByCards = useCallback((direction: 1 | -1) => {
    const scroller = scrollerRef.current

    if (!scroller) return

    const card = scroller.querySelector<HTMLElement>('[data-schedule-card]')
    const amount = card ? card.offsetWidth + CARD_GAP_PX : Math.round(scroller.clientWidth * 0.8)
    const reduceMotion =
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches

    scroller.scrollBy({
      left: direction * amount,
      behavior: reduceMotion ? 'auto' : 'smooth',
    })
  }, [])

  const handleNext = () => {
    if (!canScrollNext && hasMore && !error) {
      onLoadMore()
      return
    }

    scrollByCards(1)
  }

  const handleSelect = async (date: string, slot: ScheduleSlotView): Promise<boolean> => {
    const selected = await onSelectSlot(date, slot)

    if (selected) setModalDate(null)

    return selected
  }

  const modalDay = modalDate ? days.find((day) => day.date === modalDate) ?? null : null
  const searching = (loading || loadingMore) && days.length === 0

  if (searching) {
    return (
      <div
        className="flex min-h-28 items-center justify-center gap-2 rounded-xl bg-surface px-4 text-sm text-muted shadow-card"
        role="status">
        <Spinner className="size-4" />
        Buscando horários disponíveis…
      </div>
    )
  }

  if (days.length === 0) {
    return (
      <div className="rounded-xl bg-surface px-4 py-5 text-center shadow-card">
        <p className="text-sm font-medium text-foreground">
          Nenhum horário livre no período consultado.
        </p>
        <p className="mt-1 text-xs text-muted">
          {searchedUntil
            ? `Buscamos até ${formatFullDay(searchedUntil)}.`
            : 'Não há dias disponíveis para os critérios atuais.'}{' '}
          Ajuste a data de referência ou confira a jornada do técnico.
        </p>
        {error ? (
          <p className="mt-2 text-[13px] text-danger" role="alert">
            {error}
          </p>
        ) : null}
        <div className="mt-4 flex flex-wrap justify-center gap-2">
          {failedCount > 0 ? (
            <Button type="button" variant="secondary" size="sm" onClick={onRetryFailed}>
              Tentar dias que falharam
            </Button>
          ) : (
            <Button type="button" variant="secondary" size="sm" onClick={onReload}>
              Atualizar horários
            </Button>
          )}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <p className="text-xs text-muted" aria-live="polite">
        {days.length} dia{days.length === 1 ? '' : 's'} com horários livres
      </p>

      {error ? (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-warning-soft px-3 py-2">
          <p className="text-xs text-warning">{error}</p>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={failedCount > 0 ? onRetryFailed : onReload}>
            {failedCount > 0 ? 'Tentar novamente' : 'Atualizar'}
          </Button>
        </div>
      ) : null}

      <div className="flex items-stretch gap-1 sm:gap-2">
        <div className="hidden shrink-0 items-center pb-6 sm:flex">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className={cn(
              'size-8 rounded-full bg-surface p-0 shadow-card hover:bg-surface-2',
              !canScrollPrev && 'invisible',
            )}
            aria-label="Horários anteriores"
            disabled={!canScrollPrev}
            onClick={() => scrollByCards(-1)}>
            <ChevronLeft className="size-4" />
          </Button>
        </div>

        <div
          ref={scrollerRef}
          className="min-w-0 flex-1 overflow-x-auto pb-4 [scrollbar-color:var(--app-surface-3)_transparent] [scrollbar-width:thin] [&::-webkit-scrollbar]:h-2.5 [&::-webkit-scrollbar-button]:hidden [&::-webkit-scrollbar-corner]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-surface-3 [&::-webkit-scrollbar-thumb:hover]:bg-muted/60 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-surface-2"
          role="region"
          aria-label="Dias com horários disponíveis"
          tabIndex={0}
          onScroll={updateScrollState}>
          <ul className="flex snap-x items-stretch gap-4">
            {days.map((day) => (
              <DayCard
                key={day.date}
                day={day}
                selectedDate={selectedDate}
                selectedStart={selectedStart}
                confirmingStart={confirmingStart}
                disabled={disabled}
                onOpenMore={() => {
                  onClearWarning()
                  setModalDate(day.date)
                }}
                onSelect={(slot) => {
                  void handleSelect(day.date, slot)
                }}
              />
            ))}

            {(hasMore || loadingMore) && (
              <li
                ref={sentinelRef}
                className="flex w-8 shrink-0 items-center justify-center"
                aria-live="polite">
                {loadingMore ? (
                  <>
                    <Spinner className="size-4 text-muted" />
                    <span className="sr-only">Buscando mais horários</span>
                  </>
                ) : null}
              </li>
            )}
          </ul>
        </div>

        <div className="hidden shrink-0 items-center pb-6 sm:flex">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className={cn(
              'size-8 rounded-full bg-surface p-0 shadow-card hover:bg-surface-2',
              !canScrollNext && !hasMore && 'invisible',
            )}
            aria-label="Próximos horários"
            disabled={loadingMore || Boolean(error)}
            onClick={handleNext}>
            <ChevronRight className="size-4" />
          </Button>
        </div>
      </div>

      <ServiceOrderDayAvailabilityModal
        open={Boolean(modalDay)}
        day={modalDay}
        selectedStart={selectedStart}
        confirmingStart={confirmingStart}
        disabled={disabled}
        warning={selectionWarning}
        onClose={() => setModalDate(null)}
        onSelect={(slot) => {
          if (modalDay) void handleSelect(modalDay.date, slot)
        }}
      />
    </div>
  )
}
