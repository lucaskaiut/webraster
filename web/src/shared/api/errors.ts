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

export function parseApiError(error: unknown): ApiError {
  if (error instanceof AxiosError) {
    const status = error.response?.status ?? 0
    const body = (error.response?.data ?? {}) as ApiErrorBody

    return {
      status,
      message: body.message || STATUS_MESSAGES[status] || STATUS_MESSAGES[500],
      fieldErrors: body.errors ?? {},
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
