import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import type { ApiError } from '@/shared/api/errors'

/**
 * Aplica erros de validação (422) da API nos campos do formulário.
 */
export function applyApiErrorsToForm<T extends FieldValues>(
  form: UseFormReturn<T>,
  error: ApiError,
): void {
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    form.setError(field as Path<T>, {
      type: 'server',
      message: messages[0],
    })
  }
}
