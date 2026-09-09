import { zodResolver } from '@hookform/resolvers/zod'
import type { FieldValues, Path, Resolver, UseFormReturn } from 'react-hook-form'
import type { z } from 'zod'
import type { ApiError } from '@/shared/api/errors'

/**
 * Compatibiliza o resolver do Zod 4 com o react-hook-form quando input ≠ output
 * (ex.: z.coerce.number() e .default()).
 */
export function formResolver<T extends FieldValues>(schema: z.ZodType): Resolver<T> {
  return zodResolver(schema as never) as Resolver<T>
}

/**
 * Aplica erros de validação (422) da API nos campos do formulário.
 */
export function applyApiErrorsToForm<T extends FieldValues, TTransformed extends FieldValues = T>(
  form: UseFormReturn<T, unknown, TTransformed>,
  error: ApiError,
): void {
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    form.setError(field as Path<T>, {
      type: 'server',
      message: messages[0],
    })
  }
}
