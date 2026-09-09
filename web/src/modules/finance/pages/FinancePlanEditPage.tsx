import { useNavigate, useParams } from 'react-router'
import { PackageX } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Loading,
  Page,
  PageContent,
  PageHeader,
} from '@/shared/design-system'
import { FinancePlanForm } from '../forms/FinancePlanForm'
import { useFinancePlanQuery, useUpdateFinancePlan } from '../hooks/useFinance'

export default function FinancePlanEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const query = useFinancePlanQuery(id)
  const update = useUpdateFinancePlan(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar plano"
        description={query.data ? `Atualize o plano ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Planos', to: '/finance/plans' },
          { label: 'Editar' },
        ]}
      />
      <PageContent>
        {query.isPending && <Loading />}
        {query.isError && (
          <Card>
            <EmptyState
              icon={PackageX}
              title="Plano não encontrado"
              action={
                <ButtonLink to="/finance/plans" variant="secondary">
                  Voltar
                </ButtonLink>
              }
            />
          </Card>
        )}
        {query.data && (
          <FinancePlanForm
            mode="edit"
            initial={query.data}
            submitting={update.isPending}
            onSubmit={async (payload) => {
              await update.mutateAsync(payload)
              navigate('/finance/plans')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
