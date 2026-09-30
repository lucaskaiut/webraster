import { useId } from 'react'
import { X } from 'lucide-react'
import { Button } from './Button'
import { Field } from './Field'
import { Input } from './Input'

export const DEFAULT_PRIMARY_COLOR = '#5B5CE2'
export const DEFAULT_SECONDARY_COLOR = '#0EA5E9'

const HEX_PATTERN = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/

export interface ColorPickerProps {
  value: string | null
  onChange: (value: string | null) => void
  onBlur?: () => void
  label?: string
  hint?: string
  error?: string
  required?: boolean
  className?: string
  id?: string
  name?: string
  disabled?: boolean
  /** Cor exibida enquanto nenhuma cor foi escolhida. */
  fallback?: string
}

/**
 * Seleção de cor hexadecimal: amostra clicável (input nativo) + campo de texto.
 * Retorna `null` quando a cor é removida.
 */
export function ColorPicker({
  value,
  onChange,
  onBlur,
  label,
  hint,
  error,
  required,
  className,
  id,
  name,
  disabled,
  fallback = DEFAULT_PRIMARY_COLOR,
}: ColorPickerProps) {
  const generatedId = useId()
  const inputId = id ?? name ?? generatedId
  const preview = value && HEX_PATTERN.test(value) ? value : fallback

  const normalize = (next: string): string => (next.startsWith('#') ? next : `#${next}`)

  const handleHexBlur = () => {
    if (value) onChange(normalize(value).toUpperCase())
    onBlur?.()
  }

  return (
    <Field
      label={label}
      hint={hint}
      error={error}
      required={required}
      htmlFor={inputId}
      className={className}
    >
      <div className="flex items-center gap-2">
        <span
          className="relative size-10 shrink-0 overflow-hidden rounded-lg bg-surface-2 shadow-card"
          style={{ backgroundColor: preview }}
        >
          <input
            type="color"
            aria-label={label ? `Selecionar ${label.toLowerCase()}` : 'Selecionar cor'}
            value={preview}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value.toUpperCase())}
            className="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
          />
        </span>
        <Input
          id={inputId}
          name={name}
          value={value ?? ''}
          placeholder="#RRGGBB"
          maxLength={7}
          spellCheck={false}
          autoComplete="off"
          invalid={!!error}
          disabled={disabled}
          onChange={(event) => {
            const next = event.target.value.trim()
            onChange(next === '' ? null : next)
          }}
          onBlur={handleHexBlur}
        />
        {value !== null && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-label="Remover cor"
            disabled={disabled}
            onClick={() => onChange(null)}
            className="px-2"
          >
            <X className="size-4" />
          </Button>
        )}
      </div>
    </Field>
  )
}
