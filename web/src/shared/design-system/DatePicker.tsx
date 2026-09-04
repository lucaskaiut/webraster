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
import { CalendarDays } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import {
  formatDisplayDate,
  isDateInRange,
  maskDateInput,
  parseDisplayDate,
  parseIsoDate,
} from '@/shared/utils/date'
import { Calendar } from './Calendar'

export interface DatePickerProps extends Omit<ComponentProps<'input'>, 'type'> {
  invalid?: boolean
  placeholder?: string
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
    placeholder = 'DD/MM/AAAA',
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

  const [open, setOpen] = useState(false)
  const [popoverStyle, setPopoverStyle] = useState<CSSProperties>({})
  const [textValue, setTextValue] = useState('')
  const [focused, setFocused] = useState(false)

  const minDate = min !== undefined ? String(min) : undefined
  const maxDate = max !== undefined ? String(max) : undefined
  const stringValue = value === undefined || value === null ? '' : String(value)

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

  const isWithinBounds = (iso: string): boolean => {
    const date = parseIsoDate(iso)

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

    const iso = parseDisplayDate(trimmed)

    if (!iso || !isWithinBounds(iso)) {
      const fallback = stringValue ? formatDisplayDate(stringValue) : ''
      setTextValue(fallback)
      return stringValue
    }

    emitChange(iso)
    const formatted = formatDisplayDate(iso)
    setTextValue(formatted)
    return iso
  }

  useEffect(() => {
    if (!focused) {
      setTextValue(stringValue ? formatDisplayDate(stringValue) : '')
    }
  }, [stringValue, focused])

  const updatePosition = () => {
    const anchor = containerRef.current

    if (!anchor) {
      return
    }

    const rect = anchor.getBoundingClientRect()
    const popoverHeight = 340
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
  }, [open])

  const handleSelect = (nextValue: string) => {
    emitChange(nextValue)
    setTextValue(formatDisplayDate(nextValue))
    setOpen(false)
    textInputRef.current?.focus()
    onBlur?.({
      target: { name, value: nextValue, id: inputId },
      currentTarget: { name, value: nextValue, id: inputId },
    } as FocusEvent<HTMLInputElement>)
  }

  const handleTextChange = (event: ChangeEvent<HTMLInputElement>) => {
    const masked = maskDateInput(event.target.value)
    setTextValue(masked)

    if (masked.length === 10) {
      const iso = parseDisplayDate(masked)

      if (iso && isWithinBounds(iso)) {
        emitChange(iso)
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
            aria-label="Abrir calendário"
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
            placeholder={placeholder}
            aria-label={ariaLabel ?? 'Data'}
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
            aria-label="Calendário"
            style={popoverStyle}
            className="animate-rise-in overflow-hidden rounded-2xl border border-surface-3 bg-surface shadow-pop"
          >
            <Calendar value={stringValue} min={minDate} max={maxDate} onSelect={handleSelect} />
          </div>,
          document.body,
        )}
    </>
  )
})
