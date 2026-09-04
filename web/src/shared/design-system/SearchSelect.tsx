import { useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { Check, ChevronDown, Loader2, X } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Field } from './Field'

export interface SearchSelectOption {
  value: string
  label: string
  parent_id?: string | null
  type?: string
}

interface SearchSelectProps {
  value: string
  onChange: (value: string, option?: SearchSelectOption) => void
  loadOptions: (search: string) => Promise<SearchSelectOption[]>
  resolveLabel?: (value: string) => Promise<SearchSelectOption | null>
  label?: string
  hint?: string
  error?: string
  required?: boolean
  placeholder?: string
  emptyMessage?: string
  disabled?: boolean
  className?: string
}

export function SearchSelect({
  value,
  onChange,
  loadOptions,
  resolveLabel,
  label,
  hint,
  error,
  required,
  placeholder = 'Buscar...',
  emptyMessage = 'Nenhum resultado encontrado',
  disabled = false,
  className,
}: SearchSelectProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const loadOptionsRef = useRef(loadOptions)
  const requestIdRef = useRef(0)
  const resolvedValueRef = useRef<string | null>(null)

  const [open, setOpen] = useState(false)
  const [input, setInput] = useState('')
  const [options, setOptions] = useState<SearchSelectOption[]>([])
  const [loading, setLoading] = useState(false)
  const [highlight, setHighlight] = useState(0)
  const [selected, setSelected] = useState<SearchSelectOption | null>(null)

  useEffect(() => {
    loadOptionsRef.current = loadOptions
  })

  // Mantém o label do valor selecionado sincronizado com o valor externo.
  useEffect(() => {
    if (!value) {
      setSelected(null)
      resolvedValueRef.current = null
      return
    }

    if (resolvedValueRef.current === value) return

    resolvedValueRef.current = value
    setSelected(null)

    if (!resolveLabel) return

    let cancelled = false

    resolveLabel(value).then((option) => {
      if (!cancelled && option && resolvedValueRef.current === value) {
        setSelected(option)
      }
    })

    return () => {
      cancelled = true
    }
  }, [value, resolveLabel])

  // Busca com debounce enquanto o dropdown está aberto.
  useEffect(() => {
    if (!open) return

    const requestId = ++requestIdRef.current
    setLoading(true)

    const timer = setTimeout(async () => {
      try {
        const results = await loadOptionsRef.current(input)
        if (requestId === requestIdRef.current) {
          setOptions(results)
          setHighlight(0)
        }
      } catch {
        if (requestId === requestIdRef.current) setOptions([])
      } finally {
        if (requestId === requestIdRef.current) setLoading(false)
      }
    }, 250)

    return () => clearTimeout(timer)
  }, [input, open])

  // Fecha ao clicar fora ou pressionar Escape.
  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false)
    }

    const onKeyDown = (event: globalThis.KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  const openDropdown = () => {
    if (disabled) return
    setInput('')
    setHighlight(0)
    setOpen(true)
  }

  const selectOption = (option: SearchSelectOption) => {
    resolvedValueRef.current = option.value
    setSelected(option)
    setInput('')
    setOpen(false)
    onChange(option.value, option)
  }

  const clear = () => {
    resolvedValueRef.current = null
    setSelected(null)
    setInput('')
    setOpen(false)
    onChange('', undefined)
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (!open) {
      if (event.key === 'ArrowDown') {
        event.preventDefault()
        openDropdown()
      }
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlight((current) => Math.min(current + 1, options.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlight((current) => Math.max(current - 1, 0))
    } else if (event.key === 'Enter') {
      event.preventDefault()
      const option = options[highlight]
      if (option) selectOption(option)
    } else if (event.key === 'Escape') {
      setOpen(false)
      setInput('')
    }
  }

  const displayValue = open ? input : (selected?.label ?? '')

  return (
    <Field label={label} hint={hint} error={error} required={required} className={className}>
      <div ref={containerRef} className="relative">
        <input
          type="text"
          role="combobox"
          aria-expanded={open}
          aria-controls="search-select-listbox"
          autoComplete="off"
          disabled={disabled}
          value={displayValue}
          placeholder={open && selected ? selected.label : placeholder}
          onChange={(event) => {
            setInput(event.target.value)
            setOpen(true)
          }}
          onFocus={openDropdown}
          onKeyDown={handleKeyDown}
          className={cn(
            'h-10 w-full rounded-lg bg-surface-2 pr-9 pl-3.5 text-sm text-foreground transition-colors',
            'placeholder:text-subtle',
            'disabled:cursor-not-allowed disabled:opacity-60',
            error && 'outline-2 outline-danger/60',
          )}
        />
        {value && !open && !disabled ? (
          <button
            type="button"
            onClick={clear}
            aria-label="Limpar seleção"
            className="absolute top-1/2 right-3 flex size-5 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full text-subtle transition-colors hover:bg-surface-2 hover:text-foreground"
          >
            <X className="size-3.5" />
          </button>
        ) : (
          <ChevronDown
            className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-subtle"
            aria-hidden="true"
          />
        )}

        {open && (
          <ul
            id="search-select-listbox"
            role="listbox"
            className="absolute top-full left-0 z-40 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-surface p-1.5 shadow-pop"
          >
            {loading && options.length === 0 && (
              <li className="flex items-center justify-center gap-2 px-3 py-2 text-sm text-subtle">
                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                Buscando...
              </li>
            )}

            {!loading && options.length === 0 && (
              <li className="px-3 py-2 text-sm text-subtle">{emptyMessage}</li>
            )}

            {options.map((option, index) => (
              <li key={option.value} role="option" aria-selected={option.value === value}>
                <button
                  type="button"
                  onClick={() => selectOption(option)}
                  onMouseEnter={() => setHighlight(index)}
                  className={cn(
                    'flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors',
                    index === highlight ? 'bg-surface-2' : 'text-foreground',
                  )}
                >
                  <span className="truncate">{option.label}</span>
                  {option.value === value && <Check className="size-4 shrink-0 text-primary" aria-hidden="true" />}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Field>
  )
}
