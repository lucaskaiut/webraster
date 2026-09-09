import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { FinancePlanForm } from '../forms/FinancePlanForm'
import { useCreateFinancePlan } from '../hooks/useFinance'

export default function FinancePlanCreatePage() {
  const navigate = useNavigate()
  const create = useCreateFinancePlan()

  return (
    <Page>
      <PageHeader
        title="Novo plano"
        description="Defina valor, periodicidade e limite de dispositivos."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Planos', to: '/finance/plans' },
          { label: 'Novo' },
        ]}
      />
      <PageContent>
        <FinancePlanForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/finance/plans')
          }}
        />
      </PageContent>
    </Page>
  )
}
