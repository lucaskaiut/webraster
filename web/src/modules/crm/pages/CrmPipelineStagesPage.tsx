import { useEffect, useMemo, useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowDown, ArrowUp, Columns3, Pencil, Plus } from 'lucide-react'
import { Link } from 'react-router'
import {
  Badge,
  Button,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Spinner,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { queryKeys } from '@/shared/constants/query-keys'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { CrmStageFormModal } from '../components/CrmStageFormModal'
import { useCrmPipelinesQuery } from '../hooks/useCrm'
import { resolveDefaultPipeline } from '../lib/pipeline'
import type { CrmPipelineStage, CrmPipelineStageInput } from '../lib/types'
import { crmService } from '../services/crm.service'

function stageBadges(stage: CrmPipelineStage) {
  const items: string[] = []
  if (stage.is_initial) items.push('Inicial')
  if (stage.is_won) items.push('Ganho')
  if (stage.is_lost) items.push('Perdido')
  if (stage.is_final) items.push('Final')
  if (!stage.active) items.push('Inativa')
  return items
}

function sortStages(stages: CrmPipelineStage[]): CrmPipelineStage[] {
  return [...stages].sort((a, b) => a.position - b.position)
}

function apiErrorMessage(error: unknown): string {
  if (error && typeof error === 'object' && 'response' in error) {
    const data = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })
      .response?.data
    if (data?.errors) {
      const first = Object.values(data.errors)[0]?.[0]
      if (first) return first
    }
    if (data?.message) return data.message
  }
  if (error instanceof Error) return error.message
  return 'Não foi possível concluir a operação.'
}

export default function CrmPipelineStagesPage() {
  const { can } = usePermissions()
  const canManage = can(Permission.CRM_PIPELINE_MANAGE)
  const queryClient = useQueryClient()
  const pipelinesQuery = useCrmPipelinesQuery()
  const bootstrapAttempted = useRef(false)

  const [stageModal, setStageModal] = useState<'create' | 'edit' | null>(null)
  const [editingStage, setEditingStage] = useState<CrmPipelineStage | null>(null)
  const [stageError, setStageError] = useState<string | null>(null)

  const pipeline = useMemo(
    () => resolveDefaultPipeline(pipelinesQuery.data),
    [pipelinesQuery.data],
  )
  const pipelineId = pipeline?.id ?? null
  const stages = useMemo(() => sortStages(pipeline?.stages ?? []), [pipeline?.stages])

  const invalidatePipelines = () => {
    void queryClient.invalidateQueries({ queryKey: queryKeys.crm.pipelines() })
    void queryClient.invalidateQueries({ queryKey: queryKeys.crm.all })
  }

  const ensureDefaultPipeline = useMutation({
    mutationFn: () =>
      crmService.createPipeline({
        name: 'Vendas',
        description: 'Funil padrão',
        is_default: true,
      }),
    onSuccess: invalidatePipelines,
  })

  useEffect(() => {
    if (!pipelinesQuery.isSuccess || !canManage) {
      return
    }
    if ((pipelinesQuery.data?.length ?? 0) > 0) {
      return
    }
    if (bootstrapAttempted.current || ensureDefaultPipeline.isPending) {
      return
    }
    bootstrapAttempted.current = true
    ensureDefaultPipeline.mutate()
  }, [pipelinesQuery.isSuccess, pipelinesQuery.data, canManage, ensureDefaultPipeline.isPending])

  const createStage = useMutation({
    mutationFn: (payload: CrmPipelineStageInput) =>
      crmService.createStage(pipelineId!, payload),
    onSuccess: () => {
      invalidatePipelines()
      setStageModal(null)
      setStageError(null)
    },
    onError: (e) => setStageError(apiErrorMessage(e)),
  })

  const updateStage = useMutation({
    mutationFn: ({ stageId, payload }: { stageId: string; payload: CrmPipelineStageInput }) =>
      crmService.updateStage(pipelineId!, stageId, payload),
    onSuccess: () => {
      invalidatePipelines()
      setStageModal(null)
      setEditingStage(null)
      setStageError(null)
    },
    onError: (e) => setStageError(apiErrorMessage(e)),
  })

  const reorderStages = useMutation({
    mutationFn: (stageIds: string[]) => crmService.reorderStages(pipelineId!, stageIds),
    onSuccess: invalidatePipelines,
  })

  const moveStage = (index: number, direction: -1 | 1) => {
    const target = index + direction
    if (target < 0 || target >= stages.length || !pipelineId) {
      return
    }
    const ids = stages.map((s) => s.id)
    const [removed] = ids.splice(index, 1)
    ids.splice(target, 0, removed)
    reorderStages.mutate(ids)
  }

  const openCreateStage = () => {
    setEditingStage(null)
    setStageError(null)
    setStageModal('create')
  }

  const openEditStage = (stage: CrmPipelineStage) => {
    setEditingStage(stage)
    setStageError(null)
    setStageModal('edit')
  }

  const handleStageSubmit = (payload: CrmPipelineStageInput) => {
    if (!pipelineId) return
    if (stageModal === 'edit' && editingStage) {
      updateStage.mutate({ stageId: editingStage.id, payload })
    } else {
      createStage.mutate(payload)
    }
  }

  const stageSaving = createStage.isPending || updateStage.isPending
  const loading =
    pipelinesQuery.isPending ||
    ensureDefaultPipeline.isPending ||
    (canManage && pipelinesQuery.isSuccess && !pipeline && !ensureDefaultPipeline.isError)

  return (
    <Page>
      <PageHeader
        title="Etapas do kanban"
        description="Defina as colunas do kanban: ordem, cores e etapas inicial, ganho ou perdido."
        breadcrumb={[{ label: 'CRM' }, { label: 'Etapas' }]}
        actions={
          canManage ? (
            <Button onClick={openCreateStage} disabled={!pipelineId}>
              <Plus className="size-4" aria-hidden />
              Nova etapa
            </Button>
          ) : null
        }
      />
      <PageContent>
        {!canManage ? (
          <p className="mb-4 text-sm text-muted">
            Você pode visualizar as etapas. Para alterar, é necessária a permissão de gestão do funil.
          </p>
        ) : null}

        {ensureDefaultPipeline.isError ? (
          <p className="mb-4 rounded-xl bg-danger/10 px-3 py-2 text-sm text-danger">
            {apiErrorMessage(ensureDefaultPipeline.error)}
          </p>
        ) : null}

        {loading ? (
          <div className="flex justify-center py-16">
            <Spinner />
          </div>
        ) : !pipeline ? (
          <EmptyState
            icon={Columns3}
            title="Nenhuma etapa disponível"
            description="O funil padrão será criado automaticamente quando houver permissão de gestão ou na primeira conversa."
          />
        ) : stages.length === 0 ? (
          <EmptyState
            icon={Columns3}
            title="Sem etapas configuradas"
            description="Adicione pelo menos uma etapa inicial para o kanban exibir colunas."
            action={canManage ? <Button onClick={openCreateStage}>Criar primeira etapa</Button> : undefined}
          />
        ) : (
          <ul className="space-y-3">
            {stages.map((stage, index) => (
              <li
                key={stage.id}
                className="flex flex-wrap items-center gap-3 rounded-2xl bg-surface-2/60 px-4 py-3 shadow-card"
              >
                <span
                  className="size-3 shrink-0 rounded-full"
                  style={{ backgroundColor: stage.color ?? 'var(--color-muted)' }}
                  aria-hidden
                />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-medium text-foreground">{stage.name}</p>
                    {stageBadges(stage).map((label) => (
                      <Badge key={label} variant="neutral">
                        {label}
                      </Badge>
                    ))}
                  </div>
                  {stage.description ? (
                    <p className="mt-0.5 text-sm text-muted">{stage.description}</p>
                  ) : null}
                </div>
                <span className="text-xs text-muted">Posição {index + 1}</span>
                {canManage ? (
                  <div className="flex items-center gap-1">
                    <Button
                      variant="ghost"
                      size="sm"
                      aria-label="Mover etapa para cima"
                      disabled={index === 0 || reorderStages.isPending}
                      onClick={() => moveStage(index, -1)}
                    >
                      <ArrowUp className="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      aria-label="Mover etapa para baixo"
                      disabled={index === stages.length - 1 || reorderStages.isPending}
                      onClick={() => moveStage(index, 1)}
                    >
                      <ArrowDown className="size-4" />
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => openEditStage(stage)}>
                      <Pencil className="size-4" aria-hidden />
                      Editar
                    </Button>
                  </div>
                ) : null}
              </li>
            ))}
          </ul>
        )}

        <p className="mt-8 text-sm text-muted">
          <Link to="/crm/kanban" className="text-primary hover:underline">
            Abrir kanban de leads
          </Link>
        </p>
      </PageContent>

      <CrmStageFormModal
        open={stageModal !== null}
        onClose={() => {
          if (!stageSaving) {
            setStageModal(null)
            setEditingStage(null)
            setStageError(null)
          }
        }}
        title={stageModal === 'edit' ? 'Editar etapa' : 'Nova etapa'}
        initial={stageModal === 'edit' ? editingStage : null}
        saving={stageSaving}
        error={stageError}
        onSubmit={handleStageSubmit}
      />
    </Page>
  )
}
