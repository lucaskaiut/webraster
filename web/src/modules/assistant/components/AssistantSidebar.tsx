import { useState } from 'react'
import { MessageSquarePlus, Search, Sparkles, Trash2 } from 'lucide-react'
import { Button } from '@/shared/design-system'
import type { ConversationSummary } from '@/shared/types/models'
import { formatRelative } from '@/shared/utils/format'
import { cn } from '@/shared/utils/cn'

interface Group {
  label: string
  items: ConversationSummary[]
}

function groupByRecency(list: ConversationSummary[]): Group[] {
  const now = new Date()
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
  const day = 24 * 60 * 60 * 1000

  const buckets: Group[] = [
    { label: 'Hoje', items: [] },
    { label: 'Ontem', items: [] },
    { label: 'Esta semana', items: [] },
    { label: 'Este mês', items: [] },
    { label: 'Mais antigas', items: [] },
  ]

  for (const conversation of list) {
    const time = conversation.updated_at ? new Date(conversation.updated_at).getTime() : 0
    const diff = startOfToday - time

    if (diff <= 0) buckets[0].items.push(conversation)
    else if (diff <= day) buckets[1].items.push(conversation)
    else if (diff <= 7 * day) buckets[2].items.push(conversation)
    else if (diff <= 30 * day) buckets[3].items.push(conversation)
    else buckets[4].items.push(conversation)
  }

  return buckets.filter((bucket) => bucket.items.length > 0)
}

export function AssistantSidebar({
  conversations,
  activeId,
  search,
  onSearchChange,
  onNew,
  onSelect,
  onDelete,
}: {
  conversations: ConversationSummary[]
  activeId?: string
  search: string
  onSearchChange: (value: string) => void
  onNew: () => void
  onSelect: (id: string) => void
  onDelete: (conversation: ConversationSummary) => void
}) {
  const [confirmingId, setConfirmingId] = useState<string | null>(null)
  const groups = groupByRecency(conversations)

  return (
    <div className="flex h-full flex-col bg-surface">
      <header className="flex items-center gap-3 px-4 pt-5 pb-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-hover text-primary-foreground shadow-card">
          <Sparkles className="size-5" aria-hidden="true" />
        </span>
        <span className="min-w-0">
          <span className="block truncate text-sm font-semibold text-foreground">Assistente de IA</span>
          <span className="block truncate text-xs text-muted">Seu assistente virtual com IA</span>
        </span>
      </header>

      <div className="space-y-3 px-3 pb-3">
        <button
          type="button"
          onClick={onNew}
          className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-medium text-primary-foreground shadow-card transition-all duration-200 hover:bg-primary-hover hover:shadow-raised active:scale-[0.99]"
        >
          <MessageSquarePlus className="size-4.5" aria-hidden="true" />
          Novo chat
        </button>

        <div className="relative">
          <Search
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-subtle"
            aria-hidden="true"
          />
          <input
            type="search"
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
            placeholder="Buscar conversas..."
            aria-label="Buscar conversas"
            className="h-9 w-full rounded-lg bg-surface-2 pr-3 pl-9 text-[13px] text-foreground transition-colors outline-none placeholder:text-subtle focus:bg-surface focus:ring-2 focus:ring-ring/40"
          />
        </div>
      </div>

      <nav className="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 pb-4" aria-label="Conversas">
        {groups.length === 0 && (
          <p className="px-1 pt-2 text-[13px] text-muted">Nenhuma conversa encontrada.</p>
        )}

        {groups.map((group) => (
          <div key={group.label}>
            <h3 className="px-1 pb-1.5 text-[11px] font-semibold tracking-wider text-subtle uppercase">
              {group.label}
            </h3>
            <ul className="space-y-0.5">
              {group.items.map((conversation) => {
                const active = conversation.id === activeId

                return (
                  <li key={conversation.id} className="group relative">
                    <button
                      type="button"
                      onClick={() => onSelect(conversation.id)}
                      className={cn(
                        'relative flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition-colors',
                        active ? 'bg-surface-2' : 'hover:bg-surface-2',
                      )}
                    >
                      {active && (
                        <span className="absolute top-1/2 left-0 h-6 w-0.5 -translate-y-1/2 rounded-full bg-primary" aria-hidden="true" />
                      )}
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

                    {confirmingId === conversation.id ? (
                      <span className="absolute top-1/2 right-2 z-10 flex -translate-y-1/2 items-center gap-1 rounded-lg bg-surface p-0.5 shadow-card">
                        <Button size="sm" variant="danger" onClick={() => { onDelete(conversation); setConfirmingId(null) }}>
                          Excluir
                        </Button>
                        <Button size="sm" variant="secondary" onClick={() => setConfirmingId(null)}>
                          Cancelar
                        </Button>
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={() => setConfirmingId(conversation.id)}
                        aria-label={`Excluir ${conversation.title}`}
                        className="absolute top-1/2 right-2 hidden size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted hover:bg-surface-3 hover:text-danger group-hover:flex"
                      >
                        <Trash2 className="size-3.5" />
                      </button>
                    )}
                  </li>
                )
              })}
            </ul>
          </div>
        ))}
      </nav>
    </div>
  )
}
