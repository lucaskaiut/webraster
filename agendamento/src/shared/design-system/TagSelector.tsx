import { useState, type KeyboardEvent } from 'react'
import { X } from 'lucide-react'
import { Field } from './Field'
import { Badge } from './Badge'
import { cn } from '@/shared/utils/cn'

export interface TagSelectorProps {
  value: string[]
  onChange: (tags: string[]) => void
  label?: string
  hint?: string
  error?: string
  className?: string
  placeholder?: string
  suggestions?: string[]
}

/**
 * Seletor de tags com autocomplete. Reutilizável para posts, produtos, etc.
 */
export function TagSelector({
  value,
  onChange,
  label = 'Tags',
  hint,
  error,
  className,
  placeholder = 'Digite e pressione Enter...',
  suggestions = [],
}: TagSelectorProps) {
  const [input, setInput] = useState('')

  const add = (tag: string) => {
    const trimmed = tag.trim()
    if (!trimmed || value.includes(trimmed)) return

    onChange([...value, trimmed])
    setInput('')
  }

  const remove = (tag: string) => {
    onChange(value.filter((t) => t !== tag))
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.preventDefault()
      add(input)
    }

    if (event.key === 'Backspace' && !input && value.length > 0) {
      remove(value[value.length - 1])
    }
  }

  const filteredSuggestions = suggestions.filter(
    (s) => !value.includes(s) && s.toLowerCase().includes(input.toLowerCase()),
  )

  return (
    <Field label={label} hint={hint} error={error} className={className}>
      <div className={cn('flex flex-wrap items-center gap-1.5 rounded-xl bg-surface-2 px-3 py-2', 'has-focus:outline-2 has-focus:outline-ring')}>
        {value.map((tag) => (
          <Badge key={tag} variant="primary">
            {tag}
            <button
              type="button"
              onClick={() => remove(tag)}
              className="ml-0.5 cursor-pointer rounded-full p-0.5 transition-colors hover:bg-primary/20"
              aria-label={`Remover tag ${tag}`}
            >
              <X className="size-3" />
            </button>
          </Badge>
        ))}
        <div className="relative min-w-[120px] flex-1">
          <input
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={onKeyDown}
            placeholder={value.length === 0 ? placeholder : ''}
            className="w-full bg-transparent py-0.5 text-sm text-foreground placeholder:text-subtle focus:outline-none"
          />
          {filteredSuggestions.length > 0 && input && (
            <div className="absolute top-full left-0 z-10 mt-1 min-w-full rounded-lg bg-surface p-1 shadow-pop">
              {filteredSuggestions.slice(0, 5).map((suggestion) => (
                <button
                  key={suggestion}
                  type="button"
                  onClick={() => add(suggestion)}
                  className="block w-full cursor-pointer rounded-md px-2.5 py-1.5 text-left text-sm transition-colors hover:bg-surface-2"
                >
                  {suggestion}
                </button>
              ))}
            </div>
          )}
        </div>
      </div>
    </Field>
  )
}
