import { useMemo, useRef, useState, type DragEvent } from 'react'
import { Link } from 'react-router'
import { Page, PageContent, PageHeader, Spinner } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { formatDateTime } from '@/shared/utils/format'
import { useCrmKanbanQuery, useCrmLeadQuery, useCrmPipelinesQuery, useMoveCrmLeadStage } from '../hooks/useCrm'
import { CrmLeadDetailModal } from '../components/CrmLeadDetailModal'
import { useCrmRealtime } from '../hooks/useCrmRealtime'
import { resolveDefaultPipeline } from '../lib/pipeline'
import type { CrmPipelineStage } from '../lib/types'

export default function CrmKanbanPage() {
  useCrmRealtime()
  const { can } = usePermissions()
  const showPipelineLink = can(Permission.CRM_PIPELINE_VIEW)
  const pipelinesQuery = useCrmPipelinesQuery()
  const pipeline = useMemo(() => resolveDefaultPipeline(pipelinesQuery.data), [pipelinesQuery.data])
  const pipelineId = pipeline?.id ?? null
  const kanbanQuery = useCrmKanbanQuery(pipelineId)
  const moveLead = useMoveCrmLeadStage()
  const [draggingLeadId, setDraggingLeadId] = useState<string | null>(null)
  const [detailLeadId, setDetailLeadId] = useState<string | null>(null)
  const dragStartedRef = useRef(false)
  const detailLeadQuery = useCrmLeadQuery(detailLeadId)

  const detailLeadFromKanban = useMemo(() => {
    if (!detailLeadId) return null
    const allLeads = Object.values(kanbanQuery.data ?? {}).flat()
    return allLeads.find((item) => item.id === detailLeadId) ?? null
  }, [detailLeadId, kanbanQuery.data])

  const detailLead = detailLeadQuery.data ?? detailLeadFromKanban

  const stages = pipeline?.stages ?? []

  const handleDrop = (stage: CrmPipelineStage, event: DragEvent) => {
    event.preventDefault()
    const leadId = draggingLeadId ?? event.dataTransfer.getData('text/lead-id')
    if (!leadId || !pipelineId) return
    const allLeads = Object.values(kanbanQuery.data ?? {}).flat()
    const lead = allLeads.find((item) => item.id === leadId)
    if (!lead || lead.stage?.id === stage.id) return
    moveLead.mutate({ leadId, stageId: stage.id })
    setDraggingLeadId(null)
  }

  return (
    <Page>
      <PageHeader
        title="Kanban de leads"
        description="Arraste os cards para mover leads entre etapas."
        breadcrumb={[{ label: 'CRM' }, { label: 'Kanban' }]}
        actions={
          showPipelineLink ? (
            <Link to="/crm/pipelines" className="text-sm font-medium text-primary hover:underline">
              Gerenciar etapas
            </Link>
          ) : null
        }
      />
      <PageContent>
        {pipelinesQuery.isPending || kanbanQuery.isPending ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : !pipeline ? (
          <p className="text-muted">
            {showPipelineLink ? (
              <>
                Configure um funil em{' '}
                <Link to="/crm/pipelines" className="text-primary hover:underline">
                  CRM → Etapas
                </Link>
                .
              </>
            ) : (
              'Nenhum funil disponível.'
            )}
          </p>
        ) : (
          <div className="flex gap-3 overflow-x-auto pb-2">
            {stages.map((stage) => {
              const items = kanbanQuery.data?.[stage.id] ?? []
              return (
                <section
                  key={stage.id}
                  className="flex w-72 shrink-0 flex-col rounded-2xl bg-surface-2/60 p-3"
                  onDragOver={(e) => e.preventDefault()}
                  onDrop={(e) => handleDrop(stage, e)}
                >
                  <header className="mb-3 flex items-center justify-between px-1">
                    <h2 className="text-sm font-semibold" style={{ color: stage.color ?? undefined }}>
                      {stage.name}
                    </h2>
                    <span className="text-xs text-muted">{items.length}</span>
                  </header>
                  <div className="flex flex-1 flex-col gap-2">
                    {items.length === 0 ? (
                      <p className="py-6 text-center text-xs text-muted">Nenhum lead nesta etapa.</p>
                    ) : (
                      items.map((lead) => (
                        <article
                          key={lead.id}
                          draggable
                          onDragStart={(e) => {
                            dragStartedRef.current = true
                            setDraggingLeadId(lead.id)
                            e.dataTransfer.setData('text/lead-id', lead.id)
                          }}
                          onDragEnd={() => {
                            setDraggingLeadId(null)
                            window.setTimeout(() => {
                              dragStartedRef.current = false
                            }, 0)
                          }}
                          onClick={() => {
                            if (dragStartedRef.current) return
                            setDetailLeadId(lead.id)
                          }}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault()
                              setDetailLeadId(lead.id)
                            }
                          }}
                          role="button"
                          tabIndex={0}
                          className="cursor-grab rounded-xl bg-surface p-3 shadow-card active:cursor-grabbing"
                        >
                          <p className="font-medium text-foreground">{lead.contact.name ?? lead.contact.phone}</p>
                          <p className="mt-1 text-xs text-muted">
                            {lead.last_interaction_at ? formatDateTime(lead.last_interaction_at) : '—'}
                          </p>
                          {lead.ai_enabled ? (
                            <p className="mt-2 text-[11px] text-muted">IA ativa</p>
                          ) : null}
                        </article>
                      ))
                    )}
                  </div>
                </section>
              )
            })}
          </div>
        )}
      </PageContent>

      <CrmLeadDetailModal
        open={detailLeadId !== null}
        onClose={() => setDetailLeadId(null)}
        lead={detailLead}
        loading={detailLeadQuery.isPending && detailLead === null}
      />
    </Page>
  )
}
