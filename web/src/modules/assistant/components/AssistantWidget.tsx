import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowUpRight, MessageSquarePlus, Search, Sparkles, X } from 'lucide-react'
import { queryKeys } from '@/shared/constants/query-keys'
import { formatRelative } from '@/shared/utils/format'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { assistantService } from '../services/assistant.service'
import { useAssistantStore } from '../stores/assistant.store'
import { cn } from '@/shared/utils/cn'

/**
 * Widget flutuante de acesso rápido ao assistente, fixo no canto
 * inferior esquerdo e visível em todas as páginas autenticadas.
 */
export function AssistantWidget() {
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounce(search, 300)

  const unreadCount = useAssistantStore((state) => state.unreadCount)
  const lastReadAt = useAssistantStore((state) => state.lastReadAt)
  const markRead = useAssistantStore((state) => state.markRead)
  const setUnreadCount = useAssistantStore((state) => state.setUnreadCount)

  const conversationsQuery = useQuery({
    queryKey: queryKeys.assistant.conversations({ per_page: 10, search: debouncedSearch || undefined }),
    queryFn: () => assistantService.listConversations({ per_page: 10, search: debouncedSearch || undefined }),
    refetchInterval: open ? false : 45_000,
    refetchOnWindowFocus: false,
  })

  useEffect(() => {
    if (open) return
    const list = conversationsQuery.data?.data ?? []
    if (lastReadAt === '') {
      setUnreadCount(0)
      return
    }
    const count = list.filter((c) => c.updated_at && c.updated_at > lastReadAt).length
    setUnreadCount(count)
  }, [conversationsQuery.data, open, lastReadAt, setUnreadCount])

  const openLastChat = () => {
    const list = conversationsQuery.data?.data ?? []
    const last = list[0]
    markRead()
    setOpen(false)
    navigate(last ? `/assistant/${last.id}` : '/assistant')
  }

  const openConversation = (id: string) => {
    markRead()
    setOpen(false)
    navigate(`/assistant/${id}`)
  }

  return (
    <>
      <button
        type="button"
        onClick={() => {
          setOpen((value) => !value)
          if (!open) markRead()
        }}
        aria-label={open ? 'Fechar assistente' : 'Abrir assistente de IA'}
        className="fixed bottom-4 left-4 z-40 flex size-13 items-center justify-center rounded-full bg-gradient-to-br from-primary to-primary-hover text-primary-foreground shadow-pop transition-transform duration-200 hover:scale-105 active:scale-95"
      >
        {open ? <X className="size-6" /> : <Sparkles className="size-6" />}
        {!open && unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[11px] font-semibold text-white shadow-card">
            {unreadCount > 9 ? '9+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="animate-rise-in fixed bottom-20 left-4 z-40 flex h-[480px] w-[340px] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-surface-3 bg-surface shadow-pop">
          <header className="flex items-center gap-3 border-b border-surface-3 px-4 py-3">
            <span className="flex size-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary-hover text-primary-foreground shadow-card">
              <Sparkles className="size-4.5" />
            </span>
            <span className="min-w-0">
              <span className="block text-sm font-semibold text-foreground">Assistente de IA</span>
              <span className="block text-xs text-muted">Assistente virtual com IA</span>
            </span>
          </header>

          <div className="space-y-3 border-b border-surface-3 p-3">
            <button
              type="button"
              onClick={openLastChat}
              className="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-card transition-colors hover:bg-primary-hover"
            >
              <Sparkles className="size-4" />
              {lastReadAt === '' ? 'Iniciar conversa' : 'Continuar conversa'}
            </button>
            <div className="relative">
              <Search
                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"
                aria-hidden="true"
              />
              <input
                type="search"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar conversas..."
                aria-label="Buscar conversas"
                className="h-9 w-full rounded-lg bg-surface-2 pr-3 pl-9 text-[13px] text-foreground transition-colors outline-none placeholder:text-subtle focus:bg-surface focus:ring-2 focus:ring-ring/40"
              />
            </div>
          </div>

          <nav className="min-h-0 flex-1 overflow-y-auto px-3 py-2">
            <ul className="space-y-0.5">
              {(conversationsQuery.data?.data ?? []).map((conversation) => (
                <li key={conversation.id}>
                  <button
                    type="button"
                    onClick={() => openConversation(conversation.id)}
                    className={cn(
                      'flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition-colors hover:bg-surface-2',
                    )}
                  >
                    <span className="flex w-full items-center gap-2">
                      <span className="min-w-0 flex-1 truncate text-[13px] font-medium text-foreground">
                        {conversation.title}
                      </span>
                      <span className="shrink-0 text-[11px] text-subtle">
                        {formatRelative(conversation.updated_at)}
                      </span>
                    </span>
                    {conversation.last_message && (
                      <span className="truncate text-xs text-muted">{conversation.last_message}</span>
                    )}
                  </button>
                </li>
              ))}
              {(conversationsQuery.data?.data ?? []).length === 0 && (
                <li className="px-1 pt-2 text-[13px] text-muted">Nenhuma conversa.</li>
              )}
            </ul>
          </nav>

          <footer className="border-t border-surface-3 p-2">
            <button
              type="button"
              onClick={() => {
                markRead()
                setOpen(false)
                navigate('/assistant')
              }}
              className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-surface-2"
            >
              <span className="flex items-center gap-2">
                <MessageSquarePlus className="size-4" />
                Abrir tela completa
              </span>
              <ArrowUpRight className="size-4 text-subtle" />
            </button>
          </footer>
        </div>
      )}
    </>
  )
}
