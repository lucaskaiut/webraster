import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ChecklistForm } from '../forms/ChecklistForm'
import { useCreateChecklist } from '../hooks/useChecklists'

export default function ChecklistCreatePage() {
  const navigate = useNavigate()
  const createChecklist = useCreateChecklist()

  return (
    <Page>
      <PageHeader
        title="Novo checklist"
        description="Monte um modelo de checklist para o início ou a conclusão das ordens de serviço."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Checklists de OS', to: '/checklists' },
          { label: 'Novo checklist' },
        ]}
      />

      <PageContent>
        <ChecklistForm
          mode="create"
          submitting={createChecklist.isPending}
          onSubmit={async (payload) => {
            await createChecklist.mutateAsync(payload)
            navigate('/checklists')
          }}
        />
      </PageContent>
    </Page>
  )
}
