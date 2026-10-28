import { useEffect, useMemo, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import {
  getCalendarWeeks,
  isDateInRange,
  isSameDay,
  parseIsoDate,
  toIsoDate,
} from '@/shared/utils/date'
import { isDateWithinRange, isRangeBoundary } from '@/shared/utils/date-range'

const WEEKDAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' })

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

export interface RangeCalendarProps {
  startDate?: string
  endDate?: string
  onChange: (range: { startDate: string | null; endDate: string | null }) => void
  onComplete?: (range: { startDate: string; endDate: string }) => void
  min?: string
  max?: string
  singleSelect?: boolean
  className?: string
}

export function RangeCalendar({
  startDate,
  endDate,
  onChange,
  onComplete,
  min,
  max,
  singleSelect = false,
  className,
}: RangeCalendarProps) {
  const parsedStart = startDate ? parseIsoDate(startDate) : null
  const parsedEnd = endDate ? parseIsoDate(endDate) : null
  const today = useMemo(() => new Date(), [])
  const [viewDate, setViewDate] = useState(() => parsedStart ?? parsedEnd ?? today)

  useEffect(() => {
    if (parsedStart) {
      setViewDate(parsedStart)
    } else if (parsedEnd) {
      setViewDate(parsedEnd)
    }
  }, [startDate, endDate])

  const weeks = useMemo(() => getCalendarWeeks(viewDate), [viewDate])
  const monthLabel = capitalize(monthFormatter.format(viewDate))

  const shiftMonth = (amount: number) => {
    setViewDate((current) => new Date(current.getFullYear(), current.getMonth() + amount, 1))
  }

  const handleSelect = (iso: string) => {
    const selected = parseIsoDate(iso)

    if (!selected) {
      return
    }

    if (singleSelect) {
      onChange({ startDate: iso, endDate: iso })
      onComplete?.({ startDate: iso, endDate: iso })
      return
    }

    if (!parsedStart || parsedEnd) {
      onChange({ startDate: iso, endDate: null })
      return
    }

    if (iso < toIsoDate(parsedStart)) {
      onChange({ startDate: iso, endDate: null })
      return
    }

    onChange({ startDate: toIsoDate(parsedStart), endDate: iso })
    onComplete?.({ startDate: toIsoDate(parsedStart), endDate: iso })
  }

  return (
    <div className={cn('w-[17.5rem] select-none p-3', className)}>
      <div className="mb-3 flex items-center justify-between gap-2">
        <button
          type="button"
          aria-label="Mês anterior"
          onClick={() => shiftMonth(-1)}
          className="flex size-8 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
        >
          <ChevronLeft className="size-4" />
        </button>

        <p className="text-sm font-semibold text-foreground">{monthLabel}</p>

        <button
          type="button"
          aria-label="Próximo mês"
          onClick={() => shiftMonth(1)}
          className="flex size-8 cursor-pointer items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground"
        >
          <ChevronRight className="size-4" />
        </button>
      </div>

      <div className="mb-1 grid grid-cols-7 gap-0.5">
        {WEEKDAYS.map((weekday) => (
          <div key={weekday} className="py-1 text-center text-[11px] font-medium tracking-wide text-subtle uppercase">
            {weekday}
          </div>
        ))}
      </div>

      <div className="grid grid-cols-7 gap-0.5">
        {weeks.flat().map((day) => {
          const iso = toIsoDate(day)
          const inMonth = day.getMonth() === viewDate.getMonth()
          const disabled = !isDateInRange(day, min, max)
          const inSelection = isDateWithinRange(day, parsedStart, parsedEnd)
          const boundary = isRangeBoundary(day, parsedStart, parsedEnd)
          const isToday = isSameDay(day, today)

          return (
            <button
              key={iso}
              type="button"
              disabled={disabled}
              onClick={() => handleSelect(iso)}
              className={cn(
                'relative flex size-9 cursor-pointer items-center justify-center rounded-lg text-sm transition-colors',
                'disabled:cursor-not-allowed disabled:opacity-35',
                !disabled && !inSelection && 'hover:bg-surface-2',
                !inMonth && !inSelection && 'text-subtle',
                inMonth && !inSelection && 'text-foreground',
                isToday && !inSelection && 'font-semibold text-primary',
                inSelection && !boundary && 'bg-primary-soft text-foreground',
                boundary === 'start' && 'rounded-r-none bg-primary font-semibold text-primary-foreground',
                boundary === 'end' && 'rounded-l-none bg-primary font-semibold text-primary-foreground',
                boundary === 'both' && 'bg-primary font-semibold text-primary-foreground shadow-card',
              )}
            >
              {day.getDate()}
            </button>
          )
        })}
      </div>
    </div>
  )
}
