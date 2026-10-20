import { useNavigate, useParams } from 'react-router'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { ClipboardCheck } from 'lucide-react'
import { ChecklistForm } from '../forms/ChecklistForm'
import { useChecklistQuery, useUpdateChecklist } from '../hooks/useChecklists'

export default function ChecklistEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const query = useChecklistQuery(id)
  const updateChecklist = useUpdateChecklist(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar checklist"
        description={
          query.data ? `Atualize o modelo ${query.data.name}.` : undefined
        }
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Checklists de OS', to: '/checklists' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <div className="space-y-4 p-5">
              <Skeleton className="h-5 w-48" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-24 w-full" />
            </div>
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
          <ChecklistForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              description: query.data.description ?? '',
              type: query.data.type,
              is_active: query.data.is_active,
              questions: (query.data.questions ?? []).map((question) => ({
                prompt: question.prompt,
                answer_type: question.answer_type,
                photo_rule: question.photo_rule,
                options: (question.options ?? []).map((option) => ({ label: option.label })),
              })),
            }}
            submitting={updateChecklist.isPending}
            onSubmit={async (payload) => {
              await updateChecklist.mutateAsync(payload)
              navigate('/checklists')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
