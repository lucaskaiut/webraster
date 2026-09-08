import type { ReactNode } from 'react'
import {
  Controller,
  FormProvider,
  useFormContext,
  type FieldValues,
  type SubmitHandler,
  type UseFormReturn,
} from 'react-hook-form'
import { cn } from '@/shared/utils/cn'
import { Field } from './Field'
import { DatePicker } from './DatePicker'
import { Input, type InputProps } from './Input'
import { Textarea, type TextareaProps } from './Textarea'
import { Select, type SelectProps } from './Select'
import { SearchSelect, type SearchSelectOption } from './SearchSelect'
import { Checkbox } from './Checkbox'
import { Switch } from './Switch'
import { RadioGroup, type RadioOption } from './RadioGroup'

interface FormProps<T extends FieldValues> {
  form: UseFormReturn<T>
  onSubmit: SubmitHandler<T>
  children: ReactNode
  className?: string
}

export function Form<T extends FieldValues>({ form, onSubmit, children, className }: FormProps<T>) {
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
  ...props
}: BaseFieldProps & Omit<InputProps, 'name'>) {
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
