import { Modal } from '@/shared/design-system'
import { formatDateTime } from '@/shared/utils/format'
import type { CrmLead } from '../lib/types'
import { LeadNotesPanel } from './LeadNotesPanel'

export function CrmLeadDetailModal({
  open,
  onClose,
  lead,
  loading,
}: {
  open: boolean
  onClose: () => void
  lead: CrmLead | null
  loading?: boolean
}) {
  const title = lead?.contact.name ?? lead?.contact.phone ?? 'Lead'

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      description={
        lead?.stage?.name
          ? `Etapa: ${lead.stage.name}${lead.pipeline?.name ? ` · ${lead.pipeline.name}` : ''}`
          : undefined
      }
      size="lg"
    >
      {loading && !lead ? (
        <p className="text-sm text-muted">Carregando...</p>
      ) : lead ? (
        <div className="space-y-4">
          <dl className="grid gap-2 text-sm sm:grid-cols-2">
            <div className="rounded-xl bg-surface-2/60 px-3 py-2 shadow-card">
              <dt className="text-xs text-muted">Telefone</dt>
              <dd className="font-medium text-foreground">{lead.contact.phone ?? '—'}</dd>
            </div>
            <div className="rounded-xl bg-surface-2/60 px-3 py-2 shadow-card">
              <dt className="text-xs text-muted">Última interação</dt>
              <dd className="font-medium text-foreground">
                {lead.last_interaction_at ? formatDateTime(lead.last_interaction_at) : '—'}
              </dd>
            </div>
            <div className="rounded-xl bg-surface-2/60 px-3 py-2 shadow-card">
              <dt className="text-xs text-muted">IA</dt>
              <dd className="font-medium text-foreground">{lead.ai_enabled ? 'Ativa' : 'Desativada'}</dd>
            </div>
            <div className="rounded-xl bg-surface-2/60 px-3 py-2 shadow-card">
              <dt className="text-xs text-muted">Status</dt>
              <dd className="font-medium text-foreground">{lead.status}</dd>
            </div>
          </dl>
          <LeadNotesPanel notes={lead.notes} loading={loading} />
        </div>
      ) : null}
    </Modal>
  )
}
