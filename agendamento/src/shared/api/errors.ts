import { AxiosError } from 'axios'

export interface ApiError {
  status: number
  message: string
  fieldErrors: Record<string, string[]>
}

const STATUS_MESSAGES: Record<number, string> = {
  0: 'Não foi possível conectar ao servidor. Verifique sua conexão.',
  400: 'Requisição inválida.',
  401: 'Sua sessão expirou. Faça login novamente.',
  403: 'Você não possui permissão para executar esta ação.',
  404: 'Recurso não encontrado.',
  422: 'Verifique os dados informados e tente novamente.',
  429: 'Muitas requisições. Aguarde alguns instantes.',
  500: 'Erro interno do servidor. Tente novamente em instantes.',
}

interface ApiErrorBody {
  message?: string | null
  errors?: Record<string, string[]>
}

function firstFieldError(errors: Record<string, string[]> | undefined): string | undefined {
  if (!errors) return undefined
  for (const messages of Object.values(errors)) {
    const message = messages.find((item) => typeof item === 'string' && item.trim() !== '')
    if (message) return message
  }
  return undefined
}

/** Preferência: erro de campo (ex.: Asaas) → message da API → fallback por status. */
export function apiErrorMessage(error: ApiError): string {
  return firstFieldError(error.fieldErrors) || error.message
}

export function parseApiError(error: unknown): ApiError {
  if (error instanceof AxiosError) {
    const status = error.response?.status ?? 0
    const body = (error.response?.data ?? {}) as ApiErrorBody
    const fieldErrors = body.errors ?? {}
    const fieldMessage = firstFieldError(fieldErrors)
    const genericValidation =
      typeof body.message === 'string' &&
      /dados fornecidos são inválidos|given data was invalid/i.test(body.message)

    return {
      status,
      message:
        fieldMessage && (genericValidation || !body.message)
          ? fieldMessage
          : body.message || fieldMessage || STATUS_MESSAGES[status] || STATUS_MESSAGES[500],
      fieldErrors,
    }
  }

  if (isApiError(error)) return error

  return { status: 0, message: STATUS_MESSAGES[500], fieldErrors: {} }
}

export function isApiError(error: unknown): error is ApiError {
  return (
    typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    'message' in error &&
    'fieldErrors' in error
  )
}
