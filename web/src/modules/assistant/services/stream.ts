import { API_BASE_URL } from '@/shared/api/base-url'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import type { AssistantMessage } from '@/shared/types/models'

export interface ToolCallEvent {
  name: string
  arguments: Record<string, unknown>
}

export type StreamEvent =
  | { type: 'delta'; content: string }
  | { type: 'tool'; calls: ToolCallEvent[] }
  | { type: 'tool_result'; name: string; ok: boolean }
  | { type: 'done'; message: AssistantMessage }
  | { type: 'error'; message: string }

export interface StreamHandlers {
  onDelta?: (content: string) => void
  onTool?: (calls: ToolCallEvent[]) => void
  onToolResult?: (name: string, ok: boolean) => void
  onDone?: (message: AssistantMessage) => void
  onError?: (message: string) => void
}

function getCookie(name: string): string | undefined {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'))

  return match ? decodeURIComponent(match[1]) : undefined
}

function buildHeaders(): Headers {
  const headers = new Headers({
    Accept: 'text/event-stream',
    'Content-Type': 'application/json',
  })

  const xsrf = getCookie('XSRF-TOKEN')
  if (xsrf) headers.set('X-XSRF-TOKEN', xsrf)

  const { isMaster, availableTenants } = useSessionStore.getState()
  const { selectedTenantId } = useTenantContextStore.getState()
  const homeTenantId = availableTenants.find((tenant) => tenant.is_home)?.id

  if (isMaster && selectedTenantId && selectedTenantId !== homeTenantId) {
    headers.set('X-Tenant-Id', selectedTenantId)
  }

  return headers
}

/**
 * Envia uma mensagem e consome o stream SSE (Server-Sent Events) da resposta.
 * Retorna um AbortController para permitir cancelar a geração.
 */
export function streamChat(conversationId: string, content: string, handlers: StreamHandlers): AbortController {
  const controller = new AbortController()

  fetch(`${API_BASE_URL}/api/assistant/conversations/${conversationId}/messages`, {
    method: 'POST',
    credentials: 'include',
    headers: buildHeaders(),
    body: JSON.stringify({ content }),
    signal: controller.signal,
  })
    .then(async (response) => {
      if (!response.ok || !response.body) {
        let message = 'Falha ao processar a mensagem.'

        try {
          const body = (await response.json()) as { message?: string }
          if (body.message) message = body.message
        } catch {
          /* resposta não é JSON */
        }

        handlers.onError?.(message)
        return
      }

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      const dispatch = (raw: string) => {
        const trimmed = raw.trim()
        if (!trimmed.startsWith('data:')) return

        const payload = trimmed.slice(5).trim()
        if (!payload) return

        let event: StreamEvent
        try {
          event = JSON.parse(payload) as StreamEvent
        } catch {
          return
        }

        switch (event.type) {
          case 'delta':
            handlers.onDelta?.(event.content)
            break
          case 'tool':
            handlers.onTool?.(event.calls)
            break
          case 'tool_result':
            handlers.onToolResult?.(event.name, event.ok)
            break
          case 'done':
            handlers.onDone?.(event.message)
            break
          case 'error':
            handlers.onError?.(event.message)
            break
        }
      }

      // eslint-disable-next-line no-constant-condition
      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })

        const parts = buffer.split('\n\n')
        buffer = parts.pop() ?? ''

        for (const part of parts) dispatch(part)
      }

      if (buffer.trim()) dispatch(buffer)
    })
    .catch((error: unknown) => {
      if (error instanceof DOMException && error.name === 'AbortError') return
      handlers.onError?.('Não foi possível conectar ao servidor.')
    })

  return controller
}
