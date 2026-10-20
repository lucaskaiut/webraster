import { useParams } from 'react-router'
import { ClipboardCheck, Pencil } from 'lucide-react'
import {
  Badge,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  Loading,
  Page,
  PageContent,
  PageHeader,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import type { ChecklistQuestion } from '@/shared/types/models'
import { useChecklistQuery } from '../hooks/useChecklists'
import {
  checklistTypeBadgeVariant,
  checklistTypeLabel,
} from '../lib/labels'

export default function ChecklistDetailPage() {
  const { id } = useParams<{ id: string }>()
  const query = useChecklistQuery(id)
  const { can } = usePermissions()

  return (
    <Page>
      <PageHeader
        title={query.data?.name ?? 'Detalhes do checklist'}
        description={query.data?.description ?? undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Checklists de OS', to: '/checklists' },
          { label: 'Detalhes' },
        ]}
        actions={
          query.data && can(Permission.CHECKLIST_UPDATE) ? (
            <ButtonLink to={`/checklists/${query.data.id}/edit`}>
              <Pencil className="size-4" />
              Editar
            </ButtonLink>
          ) : undefined
        }
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <CardContent>
              <Loading />
            </CardContent>
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState
              icon={ClipboardCheck}
              title="Checklist não encontrado"
              description="O checklist pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/checklists" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <>
            <Card>
              <CardHeader title="Informações" />
              <CardContent className="space-y-3 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant={checklistTypeBadgeVariant(query.data.type)}>
                    {query.data.type_label ?? checklistTypeLabel(query.data.type)}
                  </Badge>
                  <Badge variant={query.data.is_active ? 'success' : 'neutral'}>
                    {query.data.is_active ? 'Ativo' : 'Inativo'}
                  </Badge>
                  <span className="text-muted">
                    {query.data.questions?.length ?? 0}{' '}
                    {(query.data.questions?.length ?? 0) === 1 ? 'pergunta' : 'perguntas'}
                  </span>
                </div>
                {query.data.description ? (
                  <p className="text-foreground">{query.data.description}</p>
                ) : (
                  <p className="text-muted">Sem descrição.</p>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader title="Perguntas" />
              <CardContent className="space-y-3">
                {(query.data.questions ?? []).length === 0 ? (
                  <p className="text-sm text-muted">Nenhuma pergunta cadastrada.</p>
                ) : (
                  (query.data.questions ?? []).map((question, index) => (
                    <QuestionView key={question.id} question={question} index={index} />
                  ))
                )}
              </CardContent>
            </Card>
          </>
        )}
      </PageContent>
    </Page>
  )
}

function QuestionView({
  question,
  index,
}: {
  question: ChecklistQuestion
  index: number
}) {
  return (
    <div className="space-y-2 rounded-xl bg-surface-2/60 p-4 text-sm">
      <p className="font-medium text-foreground">
        {index + 1}. {question.prompt}
      </p>
      <p className="text-[13px] text-muted">
        {question.answer_type_label ?? question.answer_type} · Fotografia:{' '}
        {question.photo_rule_label ?? question.photo_rule}
      </p>
      {question.options.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {question.options.map((option) => (
            <Badge key={option.id} variant="neutral">
              {option.label}
            </Badge>
          ))}
        </div>
      ) : null}
    </div>
  )
}
