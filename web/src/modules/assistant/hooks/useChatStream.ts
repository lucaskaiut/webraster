import { useCallback, useRef, useState } from 'react'
import { toast } from '@/shared/stores/toast.store'
import type { AssistantMessage } from '@/shared/types/models'
import { streamChat, type StreamHandlers } from '../services/stream'

let tempId = 0
let toolId = 0

function nextTempId(): string {
  tempId += 1
  return `temp-${tempId}`
}

export type ToolActivityStatus = 'running' | 'done' | 'error'

export interface ToolActivity {
  key: string
  name: string
  status: ToolActivityStatus
}

export interface ChatStream {
  messages: AssistantMessage[]
  isStreaming: boolean
  toolActivity: ToolActivity[]
  reset: (messages: AssistantMessage[]) => void
  send: (conversationId: string, content: string, onDone?: (message: AssistantMessage) => void) => void
  abort: () => void
}

/**
 * Gerencia o estado da conversa durante o streaming:
 * mensagens otimistas, acumulação de deltas e execução de ferramentas.
 */
export function useChatStream(): ChatStream {
  const [messages, setMessages] = useState<AssistantMessage[]>([])
  const [isStreaming, setIsStreaming] = useState(false)
  const [toolActivity, setToolActivity] = useState<ToolActivity[]>([])
  const controllerRef = useRef<AbortController | null>(null)

  const reset = useCallback((list: AssistantMessage[]) => {
    setMessages(list)
  }, [])

  const abort = useCallback(() => {
    controllerRef.current?.abort()
    controllerRef.current = null
    setIsStreaming(false)
    setToolActivity([])
  }, [])

  const send = useCallback(
    (conversationId: string, content: string, onDone?: (message: AssistantMessage) => void) => {
      const userMessage: AssistantMessage = {
        id: nextTempId(),
        role: 'user',
        content,
        tool_calls: null,
        tool_results: null,
        created_at: new Date().toISOString(),
      }
      const assistantId = nextTempId()
      const assistantMessage: AssistantMessage = {
        id: assistantId,
        role: 'assistant',
        content: '',
        tool_calls: null,
        tool_results: null,
        created_at: new Date().toISOString(),
      }

      setMessages((prev) => [...prev, userMessage, assistantMessage])
      setIsStreaming(true)
      setToolActivity([])

      const patchAssistant = (updater: (message: AssistantMessage) => AssistantMessage) => {
        setMessages((prev) => prev.map((m) => (m.id === assistantId ? updater(m) : m)))
      }

      const markTool = (name: string, status: ToolActivityStatus) => {
        setToolActivity((prev) => {
          const index = prev.findIndex((t) => t.name === name && t.status === 'running')
          if (index === -1) {
            toolId += 1
            return [...prev, { key: `${name}-${toolId}`, name, status }]
          }
          return prev.map((t, i) => (i === index ? { ...t, status } : t))
        })
      }

      const handlers: StreamHandlers = {
        onDelta: (delta) => {
          patchAssistant((m) => ({ ...m, content: m.content + delta }))
        },
        onTool: (calls) => {
          for (const call of calls) {
            toolId += 1
            setToolActivity((prev) => [...prev, { key: `${call.name}-${toolId}`, name: call.name, status: 'running' }])
          }
        },
        onToolResult: (name, ok) => {
          markTool(name, ok ? 'done' : 'error')
          if (!ok) {
            toast.warning('Ferramenta', `A operação "${name}" falhou.`)
          }
        },
        onDone: (message) => {
          patchAssistant(() => message)
          setIsStreaming(false)
          setToolActivity([])
          controllerRef.current = null
          onDone?.(message)
        },
        onError: (message) => {
          patchAssistant((m) => ({
            ...m,
            content: m.content !== '' ? `${m.content}\n\n> ${message}` : message,
          }))
          setIsStreaming(false)
          setToolActivity([])
          controllerRef.current = null
        },
      }

      controllerRef.current = streamChat(conversationId, content, handlers)
    },
    [],
  )

  return { messages, isStreaming, toolActivity, reset, send, abort }
}
