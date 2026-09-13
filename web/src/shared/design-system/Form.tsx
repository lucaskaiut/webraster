import { useEffect, useState, type ReactNode } from 'react'
import { FileText, Loader2, Trash2, Upload } from 'lucide-react'
import {
  Controller,
  FormProvider,
  useFormContext,
  useWatch,
  type FieldValues,
  type SubmitHandler,
  type UseFormReturn,
} from 'react-hook-form'
import { http } from '@/shared/api/http'
import { cn } from '@/shared/utils/cn'
import { Field } from './Field'
import { Button } from './Button'
import { DatePicker } from './DatePicker'
import { Input, type InputProps } from './Input'
import { Textarea, type TextareaProps } from './Textarea'
import { Select, type SelectProps } from './Select'
import { SearchSelect, type SearchSelectOption } from './SearchSelect'
import { Checkbox } from './Checkbox'
import { Switch } from './Switch'
import { RadioGroup, type RadioOption } from './RadioGroup'

interface FormProps<T extends FieldValues, TTransformed extends FieldValues = T> {
  form: UseFormReturn<T, unknown, TTransformed>
  onSubmit: SubmitHandler<TTransformed>
  children: ReactNode
  className?: string
}

export function Form<T extends FieldValues, TTransformed extends FieldValues = T>({
  form,
  onSubmit,
  children,
  className,
}: FormProps<T, TTransformed>) {
  return (
    <FormProvider {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)} noValidate className={cn('space-y-5', className)}>
        {children}
      </form>
    </FormProvider>
  )
}

interface BaseFieldProps {
  name: string
  label?: string
  hint?: string
  required?: boolean
  className?: string
}

function useFieldError(name: string): string | undefined {
  const { getFieldState, formState } = useFormContext()
  const { error } = getFieldState(name, formState)

  return error?.message as string | undefined
}

export function TextField({
  name,
  label,
  hint,
  required,
  className,
  mask,
  ...props
}: BaseFieldProps &
  Omit<InputProps, 'name'> & {
    /** Aplica máscara no valor a cada digitação (usa Controller). */
    mask?: (value: string) => string
  }) {
  const { register, control } = useFormContext()
  const error = useFieldError(name)
  const { type, ...inputProps } = props
  const withTime = type === 'datetime-local'
  const isDateField = type === 'date' || withTime

  return (
    <Field label={label} hint={hint} error={error} required={required} htmlFor={name} className={className}>
      {isDateField ? (
        <Controller
          control={control}
          name={name}
          render={({ field }) => (
            <DatePicker
              id={name}
              invalid={!!error}
              withTime={withTime}
              value={(field.value as string) ?? ''}
              onChange={field.onChange}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
              {...inputProps}
            />
          )}
        />
      ) : mask ? (
        <Controller
          control={control}
          name={name}
          render={({ field }) => (
            <Input
              id={name}
              invalid={!!error}
              type={type}
              {...inputProps}
              name={field.name}
              ref={field.ref}
              value={(field.value as string) ?? ''}
              onBlur={field.onBlur}
              onChange={(event) => field.onChange(mask(event.target.value))}
            />
          )}
        />
      ) : (
        <Input id={name} invalid={!!error} type={type} {...register(name)} {...inputProps} />
      )}
    </Field>
  )
}

export function TextareaField({
  name,
  label,
  hint,
  required,
  className,
  ...props
}: BaseFieldProps & Omit<TextareaProps, 'name'>) {
  const { register } = useFormContext()
  const error = useFieldError(name)

  return (
    <Field label={label} hint={hint} error={error} required={required} htmlFor={name} className={className}>
      <Textarea id={name} invalid={!!error} {...register(name)} {...props} />
    </Field>
  )
}

export function SelectField({
  name,
  label,
  hint,
  required,
  className,
  ...props
}: BaseFieldProps & Omit<SelectProps, 'name'>) {
  const { register } = useFormContext()
  const error = useFieldError(name)

  return (
    <Field label={label} hint={hint} error={error} required={required} htmlFor={name} className={className}>
      <Select id={name} invalid={!!error} {...register(name)} {...props} />
    </Field>
  )
}

export function FileField({
  name,
  label,
  hint,
  required,
  className,
  accept = '.pdf,image/png,image/jpeg',
  currentUrl,
}: BaseFieldProps & { accept?: string; currentUrl?: string | null }) {
  const { control } = useFormContext()
  const error = useFieldError(name)
  const [uploading, setUploading] = useState(false)
  const [uploaded, setUploaded] = useState<{ name: string; url: string } | null>(null)
  const fieldValue = useWatch({ control, name })

  useEffect(() => {
    if (!fieldValue) setUploaded(null)
  }, [fieldValue])

  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => {
        const displayUrl = uploaded?.url ?? currentUrl ?? null
        const hasFile = Boolean(field.value) || uploaded !== null

        const handleChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
          const file = event.target.files?.[0]
          if (!file) return

          setUploading(true)
          try {
            const formData = new FormData()
            formData.append('file', file)
            const response = await http.post<{ data: { url: string; path: string } }>(
              '/uploads',
              formData,
            )
            const { url, path } = response.data.data
            setUploaded({ name: file.name, url })
            field.onChange(path)
          } finally {
            setUploading(false)
            event.target.value = ''
          }
        }

        const remove = () => {
          setUploaded(null)
          field.onChange('')
        }

        return (
          <Field
            label={label}
            hint={hint}
            error={error}
            required={required}
            htmlFor={name}
            className={className}
          >
            {hasFile ? (
              <div className="flex items-center justify-between gap-3 rounded-xl bg-surface-2 px-4 py-3">
                <div className="flex min-w-0 items-center gap-2">
                  <FileText className="size-4 shrink-0 text-subtle" aria-hidden="true" />
                  {displayUrl ? (
                    <a
                      href={displayUrl}
                      target="_blank"
                      rel="noreferrer"
                      className="truncate text-sm font-medium text-primary hover:underline"
                    >
                      {uploaded?.name ?? 'Ver CRLV-e'}
                    </a>
                  ) : (
                    <span className="truncate text-sm text-foreground">
                      {uploaded?.name ?? 'CRLV-e enviado'}
                    </span>
                  )}
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={remove}
                  className="text-danger hover:bg-danger-soft hover:text-danger"
                >
                  <Trash2 className="size-3.5" />
                  Remover
                </Button>
              </div>
            ) : (
              <label
                className={cn(
                  'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-surface-3 p-8 transition-colors hover:border-primary/50',
                  uploading && 'pointer-events-none opacity-60',
                )}
              >
                {uploading ? (
                  <Loader2 className="size-7 animate-spin text-subtle" aria-hidden="true" />
                ) : (
                  <Upload className="size-7 text-subtle" aria-hidden="true" />
                )}
                <span className="text-sm text-muted">
                  {uploading ? 'Enviando...' : 'Clique para enviar o CRLV-e (PDF ou imagem)'}
                </span>
                <input type="file" accept={accept} className="sr-only" onChange={handleChange} />
              </label>
            )}
          </Field>
        )
      }}
    />
  )
}

export function SearchSelectField({
  name,
  label,
  hint,
  required,
  className,
  loadOptions,
  resolveLabel,
  placeholder,
  emptyMessage,
  disabled,
  onSelectOption,
}: BaseFieldProps & {
  loadOptions: (search: string) => Promise<SearchSelectOption[]>
  resolveLabel?: (value: string) => Promise<SearchSelectOption | null>
  placeholder?: string
  emptyMessage?: string
  disabled?: boolean
  onSelectOption?: (option: SearchSelectOption) => void
}) {
  const { control } = useFormContext()
  const error = useFieldError(name)

  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <SearchSelect
          value={(field.value as string) ?? ''}
          onChange={(value, option) => {
            field.onChange(value)
            if (option) onSelectOption?.(option)
          }}
          loadOptions={loadOptions}
          resolveLabel={resolveLabel}
          label={label}
          hint={hint}
          error={error}
          required={required}
          placeholder={placeholder}
          emptyMessage={emptyMessage}
          disabled={disabled}
          className={className}
        />
      )}
    />
  )
}

export function CheckboxField({
  name,
  label,
  description,
  className,
}: BaseFieldProps & { description?: string }) {
  const { register } = useFormContext()
  const error = useFieldError(name)

  return (
    <Field error={error} className={className}>
      <Checkbox label={label} description={description} {...register(name)} />
    </Field>
  )
}

export function SwitchField({ name, label, hint, className }: BaseFieldProps) {
  const { control } = useFormContext()
  const error = useFieldError(name)

  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <Field error={error} className={className}>
          <div className="flex items-center justify-between gap-4">
            <span className="text-sm text-foreground">{label}</span>
            <Switch checked={!!field.value} onCheckedChange={field.onChange} label={label} />
          </div>
          {hint && <p className="text-[13px] text-muted">{hint}</p>}
        </Field>
      )}
    />
  )
}

export function RadioGroupField({
  name,
  label,
  hint,
  required,
  className,
  options,
}: BaseFieldProps & { options: RadioOption[] }) {
  const { control } = useFormContext()
  const error = useFieldError(name)

  return (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <Field label={label} hint={hint} error={error} required={required} className={className}>
          <RadioGroup
            name={name}
            value={(field.value as string) ?? null}
            onChange={field.onChange}
            options={options}
            aria-label={label}
          />
        </Field>
      )}
    />
  )
}
