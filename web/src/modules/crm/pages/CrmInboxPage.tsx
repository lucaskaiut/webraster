import { useMemo, useState } from 'react'
import { Bot, MessageCircle, Send, UserRound } from 'lucide-react'
import {
  Avatar,
  Button,
  Input,
  Page,
  PageContent,
  PageHeader,
  Spinner,
} from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'
import {
  useCrmConversationsQuery,
  useCrmLeadQuery,
  useCrmMessagesQuery,
  useSendCrmMessage,
} from '../hooks/useCrm'
import { LeadNotesPanel } from '../components/LeadNotesPanel'
import { useCrmRealtime } from '../hooks/useCrmRealtime'
import { crmService } from '../services/crm.service'
import type { CrmConversation } from '../lib/types'
import { conversationAiModeLabel, conversationNeedsHumanAttention } from '../lib/conversationAiMode'

function ConversationListItem({
  item,
  active,
  onSelect,
}: {
  item: CrmConversation
  active: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        'flex w-full gap-3 rounded-xl px-3 py-3 text-left transition-colors',
        active ? 'bg-surface-2 shadow-card' : 'hover:bg-surface-2/70',
      )}
    >
      <Avatar name={item.contact.name ?? item.contact.phone ?? '?'} size="md" />
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between gap-2">
          <p className="truncate font-medium text-foreground">{item.contact.name ?? item.contact.phone ?? 'Contato'}</p>
          {item.last_message_at ? (
            <span className="shrink-0 text-[11px] text-muted">{formatDateTime(item.last_message_at)}</span>
          ) : null}
        </div>
        <p className="truncate text-sm text-muted">{item.last_message?.text ?? 'Sem mensagens'}</p>
        <div className="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-muted">
          {item.unread_count > 0 ? (
            <span className="rounded-full bg-primary px-2 py-0.5 text-primary-foreground">{item.unread_count}</span>
          ) : null}
          {item.lead?.stage?.name ? <span>{item.lead.stage.name}</span> : null}
          {item.ai_mode === 'ai_active' ? (
            <span className="inline-flex items-center gap-1"><Bot className="size-3" /> IA</span>
          ) : null}
          {conversationNeedsHumanAttention(item.ai_mode) ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-warning/15 px-2 py-0.5 text-warning">
              <UserRound className="size-3" />
              Necessita humano
            </span>
          ) : null}
        </div>
      </div>
    </button>
  )
}

export default function CrmInboxPage() {
  useCrmRealtime()
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState<string | undefined>()
  const [draft, setDraft] = useState('')

  const conversationsQuery = useCrmConversationsQuery({ search: search || undefined, filter })
  const messagesQuery = useCrmMessagesQuery(selectedId)
  const sendMessage = useSendCrmMessage()

  const conversations = conversationsQuery.data?.data ?? []
  const selected = useMemo(
    () => conversations.find((c) => c.id === selectedId) ?? null,
    [conversations, selectedId],
  )

  const selectedLeadId = selected?.lead?.id ?? null
  const leadQuery = useCrmLeadQuery(selectedLeadId)

  const messages = useMemo(() => {
    const rows = messagesQuery.data?.data ?? []
    return [...rows].reverse()
  }, [messagesQuery.data?.data])

  const selectConversation = async (id: string) => {
    setSelectedId(id)
    await crmService.markConversationRead(id)
    void conversationsQuery.refetch()
  }

  const onSend = () => {
    const text = draft.trim()
    if (!selectedId || !text) return
    sendMessage.mutate(
      { conversationId: selectedId, text },
      { onSuccess: () => setDraft('') },
    )
  }

  const notesPanel = (compact: boolean) => {
    if (!selected) {
      return <p className="text-sm text-muted">Selecione uma conversa para ver as observações do lead.</p>
    }
    if (!selectedLeadId) {
      return <p className="text-sm text-muted">Esta conversa não possui lead vinculado.</p>
    }
    return (
      <LeadNotesPanel
        notes={leadQuery.data?.notes}
        loading={leadQuery.isPending}
        compact={compact}
      />
    )
  }

  return (
    <Page className="min-h-0 flex-1 gap-4 overflow-hidden pb-0">
      <PageHeader
        title="Conversas"
        description="Atendimento comercial integrado ao WhatsApp."
        breadcrumb={[{ label: 'CRM' }, { label: 'Conversas' }]}
      />
      <PageContent className="min-h-0 flex-1 gap-3">
        <div className="grid min-h-0 flex-1 gap-3 overflow-hidden grid-cols-1 grid-rows-[minmax(0,28%)_minmax(0,1fr)_minmax(0,24%)] lg:grid-cols-[22rem_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)_minmax(0,32%)] xl:grid-cols-[22rem_minmax(0,1fr)_17rem] xl:grid-rows-1">
          <aside className="flex min-h-0 flex-col overflow-hidden rounded-2xl bg-surface-2/50 p-3 shadow-card lg:row-span-2 xl:row-span-1">
            <div className="mb-3 shrink-0 space-y-2">
              <Input placeholder="Buscar nome ou telefone" value={search} onChange={(e) => setSearch(e.target.value)} />
              <div className="flex flex-wrap gap-1">
                {[
                  { key: undefined, label: 'Todas' },
                  { key: 'unread', label: 'Não lidas' },
                  { key: 'ai', label: 'IA' },
                  { key: 'human', label: 'Precisa de humano' },
                ].map((item) => (
                  <Button
                    key={item.label}
                    size="sm"
                    variant={filter === item.key ? 'primary' : 'secondary'}
                    onClick={() => setFilter(item.key)}
                  >
                    {item.label}
                  </Button>
                ))}
              </div>
            </div>
            <div className="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain">
              {conversationsQuery.isPending ? (
                <div className="flex justify-center py-8"><Spinner /></div>
              ) : conversations.length === 0 ? (
                <p className="px-2 py-8 text-center text-sm text-muted">Nenhuma conversa encontrada.</p>
              ) : (
                conversations.map((item) => (
                  <ConversationListItem
                    key={item.id}
                    item={item}
                    active={item.id === selectedId}
                    onSelect={() => void selectConversation(item.id)}
                  />
                ))
              )}
            </div>
          </aside>

          <section className="flex min-h-0 flex-col overflow-hidden rounded-2xl bg-surface shadow-card lg:col-start-2 lg:row-start-1 xl:col-start-2 xl:row-start-1">
            {!selected ? (
              <div className="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 text-muted">
                <MessageCircle className="size-10 opacity-40" />
                <p>Selecione uma conversa para visualizar o histórico.</p>
              </div>
            ) : (
              <>
                {conversationNeedsHumanAttention(selected.ai_mode) ? (
                  <div className="mx-4 mt-3 shrink-0 rounded-xl bg-warning/10 px-3 py-2 text-sm text-foreground shadow-card">
                    <p className="font-medium">Necessita atendimento humano</p>
                    <p className="mt-0.5 text-muted">
                      A IA encaminhou esta conversa ou ocorreu um problema técnico. Assuma o atendimento e responda ao cliente.
                    </p>
                  </div>
                ) : null}
                <header className="flex shrink-0 items-center justify-between gap-3 px-4 py-3">
                  <div>
                    <p className="font-semibold text-foreground">{selected.contact.name ?? selected.contact.phone}</p>
                    <p className="text-sm text-muted">
                      {selected.contact.phone}
                      {selected.lead?.stage?.name ? ` · ${selected.lead.stage.name}` : ''}
                      {conversationAiModeLabel(selected.ai_mode)
                        ? ` · ${conversationAiModeLabel(selected.ai_mode)}`
                        : ''}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => void crmService.setAiMode(selected.id, 'human_handoff').then(() => conversationsQuery.refetch())}
                    >
                      Marcar p/ humano
                    </Button>
                    <Button
                      size="sm"
                      variant="secondary"
                      onClick={() => void crmService.setAiMode(selected.id, 'ai_active').then(() => conversationsQuery.refetch())}
                    >
                      Devolver IA
                    </Button>
                  </div>
                </header>
                <div className="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain bg-surface-2/30 px-4 py-4">
                  {messagesQuery.isPending ? (
                    <div className="flex justify-center py-8"><Spinner /></div>
                  ) : messages.length === 0 ? (
                    <p className="text-center text-sm text-muted">Este lead ainda não possui mensagens.</p>
                  ) : (
                    messages.map((message) => (
                      <div
                        key={message.id}
                        className={cn(
                          'max-w-[75%] rounded-2xl px-3 py-2 text-sm shadow-card',
                          message.direction === 'outbound'
                            ? 'ml-auto bg-primary text-primary-foreground'
                            : 'bg-surface',
                        )}
                      >
                        <p>{message.text}</p>
                        <p className={cn('mt-1 text-[10px] opacity-70', message.direction === 'outbound' && 'text-right')}>
                          {message.sent_at ? formatDateTime(message.sent_at) : ''}
                        </p>
                      </div>
                    ))
                  )}
                </div>
                <footer className="flex shrink-0 items-center gap-2 px-4 py-3">
                  <Input
                    placeholder="Digite uma mensagem..."
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault()
                        onSend()
                      }
                    }}
                  />
                  <Button onClick={onSend} disabled={sendMessage.isPending || !draft.trim()}>
                    <Send className="size-4" />
                  </Button>
                </footer>
              </>
            )}
          </section>

          <aside className="flex min-h-0 flex-col overflow-hidden rounded-2xl bg-surface-2/50 p-4 shadow-card lg:col-start-2 lg:row-start-2 xl:hidden">
            {notesPanel(true)}
          </aside>

          <aside className="hidden min-h-0 flex-col overflow-hidden rounded-2xl bg-surface-2/50 p-4 shadow-card xl:col-start-3 xl:row-start-1 xl:flex">
            {notesPanel(true)}
          </aside>
        </div>
      </PageContent>
    </Page>
  )
}
