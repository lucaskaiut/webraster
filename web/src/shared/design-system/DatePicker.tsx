import {
  forwardRef,
  useEffect,
  useId,
  useRef,
  useState,
  type ChangeEvent,
  type ComponentProps,
  type CSSProperties,
  type FocusEvent,
} from 'react'
import { createPortal } from 'react-dom'
import { CalendarDays, Clock3 } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import {
  combineIsoDateAndTime,
  extractIsoDate,
  extractLocalTime,
  formatDisplayDate,
  formatDisplayDateTime,
  isDateInRange,
  maskDateInput,
  maskDateTimeInput,
  parseDisplayDate,
  parseDisplayDateTime,
  parseIsoDate,
  parseLocalDateTime,
  toIsoDate,
  toLocalDateTimeValue,
} from '@/shared/utils/date'
import { Calendar } from './Calendar'

export interface DatePickerProps extends Omit<ComponentProps<'input'>, 'type'> {
  invalid?: boolean
  placeholder?: string
  /** Quando true, permite selecionar data e horário (`YYYY-MM-DDTHH:mm`). */
  withTime?: boolean
}

export const DatePicker = forwardRef<HTMLInputElement, DatePickerProps>(function DatePicker(
  {
    invalid,
    className,
    disabled,
    value = '',
    onChange,
    onBlur,
    name,
    id,
    min,
    max,
    withTime = false,
    placeholder,
    'aria-label': ariaLabel,
    ...props
  },
  ref,
) {
  const generatedId = useId()
  const inputId = id ?? generatedId
  const containerRef = useRef<HTMLDivElement>(null)
  const popoverRef = useRef<HTMLDivElement>(null)
  const hiddenInputRef = useRef<HTMLInputElement>(null)
  const textInputRef = useRef<HTMLInputElement>(null)
  const hourInputRef = useRef<HTMLInputElement>(null)

  const [open, setOpen] = useState(false)
  const [popoverStyle, setPopoverStyle] = useState<CSSProperties>({})
  const [textValue, setTextValue] = useState('')
  const [focused, setFocused] = useState(false)
  const [draftDate, setDraftDate] = useState('')
  const [draftTime, setDraftTime] = useState('00:00')

  const minDate = min !== undefined ? extractIsoDate(String(min)) ?? undefined : undefined
  const maxDate = max !== undefined ? extractIsoDate(String(max)) ?? undefined : undefined
  const stringValue = value === undefined || value === null ? '' : String(value)
  const resolvedPlaceholder = placeholder ?? (withTime ? 'DD/MM/AAAA HH:mm' : 'DD/MM/AAAA')

  const formatValue = (next: string) => (withTime ? formatDisplayDateTime(next) : formatDisplayDate(next))

  const setRef = (node: HTMLInputElement | null) => {
    hiddenInputRef.current = node

    if (typeof ref === 'function') {
      ref(node)
    } else if (ref) {
      ref.current = node
    }
  }

  const emitChange = (nextValue: string) => {
    onChange?.({
      target: { name, value: nextValue, id: inputId },
      currentTarget: { name, value: nextValue, id: inputId },
    } as ChangeEvent<HTMLInputElement>)
  }

  const isWithinBounds = (candidate: string): boolean => {
    const date = withTime ? parseLocalDateTime(candidate) : parseIsoDate(candidate)

    if (!date) {
      return false
    }

    return isDateInRange(date, minDate, maxDate)
  }

  const commitTextValue = (raw: string): string => {
    const trimmed = raw.trim()

    if (!trimmed) {
      emitChange('')
      setTextValue('')
      return ''
    }

    const next = withTime ? parseDisplayDateTime(trimmed) : parseDisplayDate(trimmed)

    if (!next || !isWithinBounds(next)) {
      const fallback = stringValue ? formatValue(stringValue) : ''
      setTextValue(fallback)
      return stringValue
    }

    emitChange(next)
    setTextValue(formatValue(next))
    return next
  }

  useEffect(() => {
    if (!focused) {
      setTextValue(stringValue ? formatValue(stringValue) : '')
    }
  }, [stringValue, focused, withTime])

  useEffect(() => {
    if (!open) {
      return
    }

    if (withTime) {
      setDraftDate(extractIsoDate(stringValue) ?? '')
      setDraftTime(stringValue ? extractLocalTime(stringValue) : '09:00')
      return
    }

    setDraftDate(extractIsoDate(stringValue) ?? '')
  }, [open, stringValue, withTime])

  const updatePosition = () => {
    const anchor = containerRef.current

    if (!anchor) {
      return
    }

    const rect = anchor.getBoundingClientRect()
    const popoverHeight = withTime ? 420 : 340
    const spaceBelow = window.innerHeight - rect.bottom
    const openUp = spaceBelow < popoverHeight && rect.top > popoverHeight

    setPopoverStyle({
      position: 'fixed',
      left: Math.min(rect.left, window.innerWidth - 296),
      top: openUp ? rect.top - 8 : rect.bottom + 8,
      transform: openUp ? 'translateY(-100%)' : undefined,
      zIndex: 60,
    })
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

      if (withTime && draftDate) {
        const next = combineIsoDateAndTime(draftDate, draftTime)
        if (next && isWithinBounds(next)) {
          emitChange(next)
          setTextValue(formatValue(next))
        }
      }

      setOpen(false)
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
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
  }, [open, withTime, draftDate, draftTime])

  const applyDateOnly = (nextValue: string) => {
    emitChange(nextValue)
    setTextValue(formatDisplayDate(nextValue))
    setOpen(false)
    textInputRef.current?.focus()
    onBlur?.({
      target: { name, value: nextValue, id: inputId },
      currentTarget: { name, value: nextValue, id: inputId },
    } as FocusEvent<HTMLInputElement>)
  }

  const applyDateTime = (isoDate: string, time: string, close = false) => {
    const next = combineIsoDateAndTime(isoDate, time)

    if (!next || !isWithinBounds(next)) {
      return
    }

    setDraftDate(isoDate)
    setDraftTime(time)
    emitChange(next)
    setTextValue(formatDisplayDateTime(next))

    if (close) {
      setOpen(false)
      textInputRef.current?.focus()
      onBlur?.({
        target: { name, value: next, id: inputId },
        currentTarget: { name, value: next, id: inputId },
      } as FocusEvent<HTMLInputElement>)
    }
  }

  const handleSelect = (nextDate: string) => {
    if (!withTime) {
      applyDateOnly(nextDate)
      return
    }

    applyDateTime(nextDate, draftTime || '09:00')
    hourInputRef.current?.focus()
  }

  const handleTextChange = (event: ChangeEvent<HTMLInputElement>) => {
    const masked = withTime ? maskDateTimeInput(event.target.value) : maskDateInput(event.target.value)
    setTextValue(masked)

    if (withTime && masked.length === 16) {
      const next = parseDisplayDateTime(masked)
      if (next && isWithinBounds(next)) {
        emitChange(next)
      }
    } else if (!withTime && masked.length === 10) {
      const next = parseDisplayDate(masked)
      if (next && isWithinBounds(next)) {
        emitChange(next)
      }
    } else if (!masked) {
      emitChange('')
    }
  }

  const handleTextBlur = () => {
    setFocused(false)
    const nextValue = commitTextValue(textValue)
    onBlur?.({
      target: { name, value: nextValue, id: inputId },
      currentTarget: { name, value: nextValue, id: inputId },
    } as FocusEvent<HTMLInputElement>)
  }

  const handleNow = () => {
    const now = new Date()
    const next = withTime ? toLocalDateTimeValue(now) : toIsoDate(now)

    if (!isWithinBounds(next)) {
      return
    }

    if (withTime) {
      applyDateTime(toIsoDate(now), extractLocalTime(next), true)
      return
    }

    applyDateOnly(next)
  }

  const normalizeTimeInput = (raw: string): string => {
    const digits = raw.replace(/\D/g, '').slice(0, 4)

    if (digits.length <= 2) {
      return digits
    }

    return `${digits.slice(0, 2)}:${digits.slice(2)}`
  }

  const fieldClasses = cn(
    'group/date flex h-10 w-full min-w-38 items-center rounded-lg bg-surface-2 transition-colors',
    'shadow-[inset_0_0_0_1px_var(--app-surface-3)]',
    'hover:shadow-[inset_0_0_0_1px_color-mix(in_srgb,var(--app-fg-subtle)_55%,transparent)]',
    (open || focused) && 'shadow-[inset_0_0_0_2px_color-mix(in_srgb,var(--app-primary)_45%,transparent)]',
    disabled && 'cursor-not-allowed opacity-60',
    invalid && 'shadow-[inset_0_0_0_2px_color-mix(in_srgb,var(--app-danger)_55%,transparent)]',
  )

  return (
    <>
      <div ref={containerRef} className={cn('relative', className)}>
        <input
          ref={setRef}
          type="hidden"
          id={inputId}
          name={name}
          value={stringValue}
          disabled={disabled}
          aria-invalid={invalid || undefined}
          {...props}
        />

        <div className={fieldClasses}>
          <button
            type="button"
            tabIndex={-1}
            disabled={disabled}
            aria-label={withTime ? 'Abrir calendário e horário' : 'Abrir calendário'}
            onClick={() => {
              if (disabled) {
                return
              }

              setOpen((current) => !current)
              textInputRef.current?.focus()
            }}
            className="flex size-10 shrink-0 cursor-pointer items-center justify-center text-subtle transition-colors hover:text-muted disabled:cursor-not-allowed disabled:pointer-events-none"
          >
            <CalendarDays className={cn('size-4', open && 'text-primary')} aria-hidden="true" />
          </button>

          <input
            ref={textInputRef}
            type="text"
            inputMode="numeric"
            autoComplete="off"
            disabled={disabled}
            value={textValue}
            placeholder={resolvedPlaceholder}
            aria-label={ariaLabel ?? (withTime ? 'Data e horário' : 'Data')}
            aria-invalid={invalid || undefined}
            onFocus={() => setFocused(true)}
            onChange={handleTextChange}
            onBlur={handleTextBlur}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                commitTextValue(textValue)
                setOpen(false)
                textInputRef.current?.blur()
              }

              if (event.key === 'ArrowDown' && !open) {
                event.preventDefault()
                setOpen(true)
              }
            }}
            className="h-full min-w-0 flex-1 bg-transparent pr-3 text-sm text-foreground outline-none placeholder:text-subtle disabled:cursor-not-allowed"
          />
        </div>
      </div>

      {open &&
        createPortal(
          <div
            ref={popoverRef}
            role="dialog"
            aria-label={withTime ? 'Calendário e horário' : 'Calendário'}
            style={popoverStyle}
            className="animate-rise-in overflow-hidden rounded-2xl border border-surface-3 bg-surface shadow-pop"
          >
            <Calendar
              value={draftDate || extractIsoDate(stringValue) || undefined}
              min={minDate}
              max={maxDate}
              onSelect={handleSelect}
              showTodayAction={!withTime}
            />

            {withTime && (
              <div className="space-y-3 border-t border-surface-2 px-3 pb-3">
                <div className="flex items-center gap-2 pt-3">
                  <Clock3 className="size-4 text-subtle" aria-hidden="true" />
                  <span className="text-[13px] font-medium text-muted">Horário</span>
                </div>

                <div className="flex items-center gap-2">
                  <input
                    ref={hourInputRef}
                    type="text"
                    inputMode="numeric"
                    autoComplete="off"
                    aria-label="Horário"
                    placeholder="HH:mm"
                    value={draftTime}
                    onChange={(event) => {
                      const nextTime = normalizeTimeInput(event.target.value)
                      setDraftTime(nextTime)

                      if (draftDate && /^\d{2}:\d{2}$/.test(nextTime)) {
                        applyDateTime(draftDate, nextTime)
                      }
                    }}
                    onBlur={() => {
                      if (!/^\d{2}:\d{2}$/.test(draftTime)) {
                        setDraftTime(stringValue ? extractLocalTime(stringValue) : '09:00')
                        return
                      }

                      if (draftDate) {
                        applyDateTime(draftDate, draftTime)
                      }
                    }}
                    className="h-10 w-24 rounded-lg bg-surface-2 px-3 text-sm text-foreground outline-none shadow-[inset_0_0_0_1px_var(--app-surface-3)] focus:shadow-[inset_0_0_0_2px_color-mix(in_srgb,var(--app-primary)_45%,transparent)]"
                  />

                  <div className="ml-auto flex gap-2">
                    <button
                      type="button"
                      onClick={handleNow}
                      className="cursor-pointer rounded-lg px-3 py-2 text-[13px] font-medium text-primary transition-colors hover:bg-primary-soft"
                    >
                      Agora
                    </button>
                    <button
                      type="button"
                      disabled={!draftDate || !/^\d{2}:\d{2}$/.test(draftTime)}
                      onClick={() => {
                        if (!draftDate) {
                          return
                        }

                        applyDateTime(draftDate, draftTime, true)
                      }}
                      className="cursor-pointer rounded-lg bg-primary px-3 py-2 text-[13px] font-medium text-primary-foreground transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-40"
                    >
                      Confirmar
                    </button>
                  </div>
                </div>
              </div>
            )}

          </div>,
          document.body,
        )}
    </>
  )
})
