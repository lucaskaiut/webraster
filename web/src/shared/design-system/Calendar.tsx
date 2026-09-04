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

const WEEKDAYS = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

const monthFormatter = new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' })

function capitalize(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1)
}

export interface CalendarProps {
  value?: string
  onSelect: (value: string) => void
  min?: string
  max?: string
  className?: string
}

export function Calendar({ value, onSelect, min, max, className }: CalendarProps) {
  const selectedDate = value ? parseIsoDate(value) : null
  const today = useMemo(() => new Date(), [])
  const [viewDate, setViewDate] = useState(() => selectedDate ?? today)

  useEffect(() => {
    if (selectedDate) {
      setViewDate(selectedDate)
    }
  }, [value])

  const weeks = useMemo(() => getCalendarWeeks(viewDate), [viewDate])
  const monthLabel = capitalize(monthFormatter.format(viewDate))

  const shiftMonth = (amount: number) => {
    setViewDate((current) => new Date(current.getFullYear(), current.getMonth() + amount, 1))
  }

  const selectToday = () => {
    const iso = toIsoDate(today)

    if (!isDateInRange(today, min, max)) {
      return
    }

    onSelect(iso)
    setViewDate(today)
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
          const selected = selectedDate ? isSameDay(day, selectedDate) : false
          const isToday = isSameDay(day, today)
          const disabled = !isDateInRange(day, min, max)

          return (
            <button
              key={iso}
              type="button"
              disabled={disabled}
              onClick={() => onSelect(iso)}
              className={cn(
                'flex size-9 cursor-pointer items-center justify-center rounded-lg text-sm transition-colors',
                'disabled:cursor-not-allowed disabled:opacity-35',
                !selected && !disabled && 'hover:bg-surface-2',
                !inMonth && !selected && 'text-subtle',
                inMonth && !selected && 'text-foreground',
                isToday && !selected && 'font-semibold text-primary',
                selected && 'bg-primary font-semibold text-primary-foreground shadow-card hover:bg-primary-hover',
              )}
            >
              {day.getDate()}
            </button>
          )
        })}
      </div>

      <div className="mt-3 flex justify-center border-t border-surface-2 pt-3">
        <button
          type="button"
          onClick={selectToday}
          className="cursor-pointer rounded-full px-3 py-1.5 text-[13px] font-medium text-primary transition-colors hover:bg-primary-soft"
        >
          Hoje
        </button>
      </div>
    </div>
  )
}
