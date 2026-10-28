import { useEffect, useId, useRef, useState, type KeyboardEvent, type Ref } from 'react'
import { Controller, useFormContext } from 'react-hook-form'
import { Loader2, MapPin } from 'lucide-react'
import { Field } from '@/shared/design-system/Field'
import { Input } from '@/shared/design-system/Input'
import { cn } from '@/shared/utils/cn'
import {
  serviceOrdersService,
  type AddressSuggestion,
} from '../services/service-orders.service'

/** A busca só é feita depois de duas palavras digitadas. */
const MIN_WORDS = 2
const DEBOUNCE_MS = 600

function hasMinimumWords(value: string): boolean {
  return value.trim().split(/\s+/).filter(Boolean).length >= MIN_WORDS
}

interface AddressAutocompleteFieldProps {
  name: string
  label?: string
  hint?: string
  required?: boolean
  className?: string
  placeholder?: string
  disabled?: boolean
}

/**
 * Campo de endereço com sugestões (Google Places, com fallback para
 * OpenStreetMap). O texto livre continua permitido; as sugestões só são
 * buscadas depois de duas palavras e, ao escolher uma, o endereço completo
 * é gravado.
 */
export function AddressAutocompleteField(props: AddressAutocompleteFieldProps) {
  const { control } = useFormContext()

  return (
    <Controller
      control={control}
      name={props.name}
      render={({ field, fieldState }) => (
        <AddressAutocompleteInput
          {...props}
          id={props.name}
          value={(field.value as string) ?? ''}
          onChange={field.onChange}
          onBlur={field.onBlur}
          inputRef={field.ref}
          error={fieldState.error?.message}
        />
      )}
    />
  )
}

interface AddressAutocompleteInputProps extends AddressAutocompleteFieldProps {
  id: string
  value: string
  onChange: (value: string) => void
  onBlur: () => void
  inputRef: Ref<HTMLInputElement>
  error?: string
}

function AddressAutocompleteInput({
  id,
  value,
  onChange,
  onBlur,
  inputRef,
  error,
  label,
  hint,
  required,
  className,
  placeholder = 'Digite o endereço...',
  disabled = false,
}: AddressAutocompleteInputProps) {
  const generatedId = useId()
  const inputId = id || `address-${generatedId}`
  const listboxId = `${inputId}-listbox`
  const containerRef = useRef<HTMLDivElement>(null)
  const selectedRef = useRef<string | null>(null)
  const requestIdRef = useRef(0)

  const [open, setOpen] = useState(false)
  const [hasTyped, setHasTyped] = useState(false)
  const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([])
  const [loading, setLoading] = useState(false)
  const [failed, setFailed] = useState(false)
  const [highlight, setHighlight] = useState(0)

  const term = value.trim()
  const selected = selectedRef.current === term
  const showDropdown = open && hasTyped && hasMinimumWords(term) && !selected

  // Busca com debounce enquanto o usuário digita.
  useEffect(() => {
    if (!showDropdown) {
      requestIdRef.current += 1
      setSuggestions([])
      setLoading(false)
      setFailed(false)
      return
    }

    const requestId = ++requestIdRef.current
    setLoading(true)
    setFailed(false)

    const timer = setTimeout(() => {
      serviceOrdersService
        .searchAddress(term)
        .then((results) => {
          if (requestId !== requestIdRef.current) return

          setSuggestions(results)
          setHighlight(0)
        })
        .catch(() => {
          if (requestId !== requestIdRef.current) return

          setSuggestions([])
          setFailed(true)
        })
        .finally(() => {
          if (requestId === requestIdRef.current) setLoading(false)
        })
    }, DEBOUNCE_MS)

    return () => clearTimeout(timer)
  }, [showDropdown, term])

  // Fecha ao clicar fora.
  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)

    return () => document.removeEventListener('pointerdown', onPointerDown)
  }, [open])

  const selectSuggestion = (suggestion: AddressSuggestion) => {
    selectedRef.current = suggestion.address
    setHasTyped(false)
    onChange(suggestion.address)
    setOpen(false)
    setSuggestions([])
    setFailed(false)
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (!open) {
      if (event.key === 'ArrowDown') {
        event.preventDefault()
        setOpen(true)
      }

      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlight((current) => Math.min(current + 1, Math.max(suggestions.length - 1, 0)))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlight((current) => Math.max(current - 1, 0))
    } else if (event.key === 'Enter') {
      const suggestion = suggestions[highlight]

      if (suggestion) {
        // Evita submeter o formulário ao escolher uma sugestão.
        event.preventDefault()
        selectSuggestion(suggestion)
      } else if (loading) {
        event.preventDefault()
      } else {
        setOpen(false)
      }
    } else if (event.key === 'Escape') {
      event.preventDefault()
      setOpen(false)
    } else if (event.key === 'Tab') {
      setOpen(false)
    }
  }

  const activeOptionId =
    showDropdown && suggestions[highlight]
      ? `${listboxId}-option-${highlight}`
      : undefined

  return (
    <Field
      label={label}
      hint={hint}
      error={error}
      required={required}
      htmlFor={inputId}
      className={className}>
      <div ref={containerRef} className="relative">
        <Input
          ref={inputRef}
          id={inputId}
          name={id}
          type="text"
          role="combobox"
          aria-label={label}
          aria-expanded={showDropdown}
          aria-controls={listboxId}
          aria-autocomplete="list"
          aria-activedescendant={activeOptionId}
          aria-invalid={Boolean(error) || undefined}
          autoComplete="off"
          invalid={Boolean(error)}
          className={loading ? 'pr-10' : undefined}
          disabled={disabled}
          value={value}
          placeholder={placeholder}
          onChange={(event) => {
            setOpen(true)
            setHasTyped(true)
            onChange(event.target.value)
          }}
          onFocus={() => {
            if (!disabled) setOpen(true)
          }}
          onBlur={onBlur}
          onKeyDown={handleKeyDown}
        />

        {loading && (
          <Loader2
            className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 animate-spin text-subtle"
            aria-hidden="true"
          />
        )}

        {showDropdown && (
          <ul
            id={listboxId}
            role="listbox"
            className="absolute top-full left-0 z-40 mt-1 max-h-72 w-full overflow-auto rounded-xl bg-surface p-1.5 shadow-pop">
            {loading && suggestions.length === 0 && (
              <li className="flex items-center justify-center gap-2 px-3 py-2 text-sm text-subtle">
                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                Buscando endereços...
              </li>
            )}

            {!loading && suggestions.length === 0 && (
              <li className="px-3 py-2 text-sm text-subtle">
                {failed
                  ? 'Não foi possível buscar endereços agora. Você pode digitar manualmente.'
                  : 'Nenhum endereço encontrado. Você pode digitar manualmente.'}
              </li>
            )}

            {suggestions.map((suggestion, index) => (
              <li
                key={`${suggestion.address}:${suggestion.latitude}:${suggestion.longitude}`}
                id={`${listboxId}-option-${index}`}
                role="option"
                aria-selected={index === highlight}>
                <button
                  type="button"
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => selectSuggestion(suggestion)}
                  onMouseEnter={() => setHighlight(index)}
                  className={cn(
                    'flex w-full cursor-pointer items-start gap-2 rounded-lg px-3 py-2 text-left transition-colors',
                    index === highlight ? 'bg-surface-2' : 'text-foreground',
                  )}>
                  <MapPin
                    className="mt-0.5 size-4 shrink-0 text-muted"
                    aria-hidden="true"
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">
                      {suggestion.title}
                    </span>
                    {suggestion.subtitle && (
                      <span className="block truncate text-xs text-muted">
                        {suggestion.subtitle}
                      </span>
                    )}
                  </span>
                </button>
              </li>
            ))}

            <li className="px-3 py-1.5 text-[10px] text-subtle">
              Sugestões de endereço
            </li>
          </ul>
        )}
      </div>
    </Field>
  )
}
