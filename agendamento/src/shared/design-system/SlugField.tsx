import { useFormContext } from 'react-hook-form'
import { Field } from './Field'
import { Input } from './Input'
import type { FieldValues, Path } from 'react-hook-form'
import { useState } from 'react'

interface SlugFieldProps<T extends FieldValues> {
  name: Path<T>
  sourceField: Path<T>
  label?: string
  hint?: string
  className?: string
}

/**
 * Campo de slug com geração automática a partir de um campo fonte (ex.: título).
 * Permite edição manual.
 */
export function SlugField<T extends FieldValues>({
  name,
  sourceField,
  label = 'Slug',
  hint = 'Gerado automaticamente a partir do título. Pode ser editado manualmente.',
  className,
}: SlugFieldProps<T>) {
  const { register, watch, setValue, getFieldState, formState } = useFormContext<T>()
  const { error } = getFieldState(name, formState)
  const sourceValue = watch(sourceField)
  const slugValue = watch(name)
  const [manualEdit, setManualEdit] = useState(false)

  const generate = () => {
    const slug = (typeof sourceValue === 'string' ? sourceValue : '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '')

    setValue(name, slug as T[typeof name], { shouldValidate: true })
  }

  return (
    <Field label={label} hint={hint} error={error?.message as string | undefined} className={className}>
      <div className="flex items-center gap-2">
        <Input
          placeholder="slug-do-post"
          {...register(name)}
          invalid={!!error}
          disabled={!manualEdit}
          className={!manualEdit ? 'opacity-70' : ''}
        />
        {slugValue ? (
          <button
            type="button"
            onClick={() => setManualEdit((v) => !v)}
            className="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-primary transition-colors hover:bg-primary-soft"
          >
            {manualEdit ? 'Automático' : 'Manual'}
          </button>
        ) : (
          <button
            type="button"
            onClick={generate}
            className="shrink-0 rounded-lg px-2 py-1 text-xs font-medium text-primary transition-colors hover:bg-primary-soft"
          >
            Gerar
          </button>
        )}
      </div>
    </Field>
  )
}
