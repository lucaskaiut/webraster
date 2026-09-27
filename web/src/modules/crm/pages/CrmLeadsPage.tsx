import { useState } from 'react'
import { Link } from 'react-router'
import { Page, PageContent, PageHeader, Spinner, Input } from '@/shared/design-system'
import { formatDateTime } from '@/shared/utils/format'
import { useCrmLeadsQuery, useCrmPipelinesQuery } from '../hooks/useCrm'
import { resolveDefaultPipeline } from '../lib/pipeline'

export default function CrmLeadsPage() {
  const [search, setSearch] = useState('')
  const pipelinesQuery = useCrmPipelinesQuery()
  const pipelineId = resolveDefaultPipeline(pipelinesQuery.data)?.id
  const leadsQuery = useCrmLeadsQuery({ pipeline_id: pipelineId, search: search || undefined })

  const leads = leadsQuery.data?.data ?? []

  return (
    <Page>
      <PageHeader
        title="Leads"
        description="Lista de oportunidades comerciais."
        breadcrumb={[{ label: 'CRM' }, { label: 'Leads' }]}
        actions={<Input placeholder="Buscar..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-56" />}
      />
      <PageContent>
        {leadsQuery.isPending ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : leads.length === 0 ? (
          <p className="text-muted">Nenhum lead encontrado.</p>
        ) : (
          <div className="overflow-x-auto rounded-2xl bg-surface shadow-card">
            <table className="w-full min-w-[640px] text-left text-sm">
              <thead className="bg-surface-2/80 text-muted">
                <tr>
                  <th className="px-4 py-3 font-medium">Lead</th>
                  <th className="px-4 py-3 font-medium">Contato</th>
                  <th className="px-4 py-3 font-medium">Etapa</th>
                  <th className="px-4 py-3 font-medium">IA</th>
                  <th className="px-4 py-3 font-medium">Última interação</th>
                </tr>
              </thead>
              <tbody>
                {leads.map((lead) => (
                  <tr key={lead.id} className="border-t border-transparent hover:bg-surface-2/40">
                    <td className="px-4 py-3">
                      <Link to="/crm/inbox" className="font-medium text-primary hover:underline">
                        {lead.contact.name ?? 'Sem nome'}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{lead.contact.phone ?? '—'}</td>
                    <td className="px-4 py-3">{lead.stage?.name ?? '—'}</td>
                    <td className="px-4 py-3">{lead.ai_enabled ? 'Ativa' : 'Desativada'}</td>
                    <td className="px-4 py-3">
                      {lead.last_interaction_at ? formatDateTime(lead.last_interaction_at) : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </PageContent>
    </Page>
  )
}
