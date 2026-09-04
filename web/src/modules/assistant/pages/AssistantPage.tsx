import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { ArrowLeft, Menu, Sparkles, X } from 'lucide-react'
import { ButtonLink, Loading } from '@/shared/design-system'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { useSessionStore } from '@/shared/stores/session.store'
import type { ConversationSummary } from '@/shared/types/models'
import { assistantService } from '../services/assistant.service'
import {
  useConversationsQuery,
  useCreateConversation,
  useDeleteConversation,
} from '../hooks/useAssistant'
import { useChatStream } from '../hooks/useChatStream'
import { AssistantSidebar } from '../components/AssistantSidebar'
import { MessageBubble } from '../components/MessageBubble'
import { ChatInput } from '../components/ChatInput'
import { Suggestions } from '../components/Suggestions'
import { ToolActivityList } from '../components/ToolActivityList'
import { cn } from '@/shared/utils/cn'

function greeting(): string {
  const hour = new Date().getHours()
  if (hour < 12) return 'Bom dia'
  if (hour < 18) return 'Boa tarde'
  return 'Boa noite'
}

export default function AssistantPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const user = useSessionStore((state) => state.user)

  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search, 300)
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [loadingMessages, setLoadingMessages] = useState(false)

  const conversationsQuery = useConversationsQuery({ per_page: 100, search: debouncedSearch || undefined })
  const createConversation = useCreateConversation()
  const deleteConversation = useDeleteConversation()

  const chat = useChatStream()
  const resetChat = chat.reset
  const abortStream = chat.abort
  const loadedIdRef = useRef<string | null>(id ?? null)

  useEffect(() => {
    if (id === loadedIdRef.current) return

    abortStream()
    loadedIdRef.current = id ?? null

    if (!id) {
      resetChat([])
      setLoadingMessages(false)
      return
    }

    setLoadingMessages(true)
    assistantService
      .getConversation(id)
      .then((conversation) => {
        if (id === loadedIdRef.current) resetChat(conversation.messages)
      })
      .finally(() => setLoadingMessages(false))
  }, [id, resetChat, abortStream])

  const invalidateConversations = () => {
    queryClient.invalidateQueries({ queryKey: queryKeys.assistant.all })
  }

  const handleNew = async () => {
    const conversation = await createConversation.mutateAsync()
    loadedIdRef.current = conversation.id
    navigate(`/assistant/${conversation.id}`)
    setSidebarOpen(false)
  }

  const handleSelect = (conversationId: string) => {
    navigate(`/assistant/${conversationId}`)
    setSidebarOpen(false)
  }

  const handleDelete = (conversation: ConversationSummary) => {
    deleteConversation.mutate(conversation.id, {
      onSuccess: () => {
        if (conversation.id === id) navigate('/assistant', { replace: true })
      },
    })
  }

  const handleSend = async (content: string) => {
    if (id) {
      chat.send(id, content, invalidateConversations)
      return
    }

    const conversation = await createConversation.mutateAsync()
    loadedIdRef.current = conversation.id
    navigate(`/assistant/${conversation.id}`, { replace: true })
    chat.send(conversation.id, content, invalidateConversations)
  }

  const updateSearch = (value: string) => {
    setSearch(value)
    setSearchParams((params) => {
      if (value) params.set('search', value)
      else params.delete('search')
      return params
    }, { replace: true })
  }

  const conversations = conversationsQuery.data?.data ?? []
  const visibleMessages = chat.messages.filter(
    (message) => message.role === 'user' || (message.role === 'assistant' && message.content !== ''),
  )
  const firstName = user?.name?.split(' ')[0] ?? ''

  const sidebar = (
    <AssistantSidebar
      conversations={conversations}
      activeId={id}
      search={search}
      onSearchChange={updateSearch}
      onNew={handleNew}
      onSelect={handleSelect}
      onDelete={handleDelete}
    />
  )

  return (
    <div className="app-viewport-height flex overflow-hidden bg-background">
      <aside className="hidden w-72 shrink-0 border-r border-surface-3 md:block">{sidebar}</aside>

      {sidebarOpen && (
        <div className="fixed inset-0 z-40 flex md:hidden">
          <div className="animate-fade-in absolute inset-0 bg-overlay" onClick={() => setSidebarOpen(false)} aria-hidden="true" />
          <div className="animate-slide-in relative flex h-full w-80 max-w-[85vw] flex-col shadow-pop">
            <div className="flex items-center justify-between border-b border-surface-3 bg-surface px-3 py-2">
              <span className="px-1 text-sm font-semibold text-foreground">Conversas</span>
              <button
                type="button"
                onClick={() => setSidebarOpen(false)}
                aria-label="Fechar"
                className="flex size-8 items-center justify-center rounded-lg text-muted hover:bg-surface-2 hover:text-foreground"
              >
                <X className="size-4.5" />
              </button>
            </div>
            {sidebar}
          </div>
        </div>
      )}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 shrink-0 items-center gap-1.5 border-b border-surface-3 bg-surface px-3">
          <button
            type="button"
            onClick={() => setSidebarOpen(true)}
            aria-label="Abrir conversas"
            className="flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-2 hover:text-foreground md:hidden"
          >
            <Menu className="size-5" />
          </button>

          <ButtonLink to="/dashboard" variant="ghost" size="sm">
            <ArrowLeft className="size-4" />
            <span className="hidden sm:inline">Voltar</span>
          </ButtonLink>
        </header>

        <main className="min-h-0 flex-1 overflow-y-auto">
          <div
            className={cn(
              'mx-auto max-w-3xl px-4 py-6',
              visibleMessages.length === 0 && !loadingMessages && 'flex min-h-full flex-col justify-center',
            )}
          >
            {loadingMessages ? (
              <Loading label="Carregando conversa..." />
            ) : visibleMessages.length === 0 ? (
              <EmptyState name={firstName} onSelect={handleSend} />
            ) : (
              <div className="flex flex-col gap-5">
                {visibleMessages.map((message, index) => (
                  <MessageBubble
                    key={message.id}
                    message={message}
                    streaming={chat.isStreaming && index === visibleMessages.length - 1 && message.role === 'assistant'}
                  />
                ))}
                <ToolActivityList activities={chat.toolActivity} />
              </div>
            )}
          </div>
        </main>

        <footer className="shrink-0 border-t border-surface-3 px-4 py-3">
          <div className="mx-auto max-w-3xl">
            <ChatInput disabled={chat.isStreaming} onSend={handleSend} onStop={chat.abort} />
            <p className="mt-2 text-center text-[11px] text-subtle">
              O assistente pode cometer erros. Confirme informações importantes.
            </p>
          </div>
        </footer>
      </div>
    </div>
  )
}

function EmptyState({ name, onSelect }: { name: string; onSelect: (prompt: string) => void }) {
  return (
    <div className="relative isolate flex flex-col items-center text-center">
      <div
        className="pointer-events-none absolute top-0 -z-10 size-72 -translate-y-1/2 rounded-full bg-primary-soft opacity-70 blur-3xl"
        aria-hidden="true"
      />

      <div className="animate-rise-in flex flex-col items-center gap-5">
        <span className="flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-primary-hover text-primary-foreground shadow-raised">
          <Sparkles className="size-7" aria-hidden="true" />
        </span>

        <div className="space-y-1.5">
          <p className="text-sm font-medium text-muted">{greeting()}</p>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">
            Olá, {name}!
          </h1>
          <p className="text-lg text-muted">Como posso ajudar hoje?</p>
        </div>

        <p className="max-w-md text-sm leading-relaxed text-muted">
          Pergunte, peça para realizar ações ou explore os recursos disponíveis no assistente.
        </p>
      </div>

      <div className="animate-rise-in mt-10 w-full text-left" style={{ animationDelay: '60ms' }}>
        <Suggestions onSelect={onSelect} />
      </div>
    </div>
  )
}
