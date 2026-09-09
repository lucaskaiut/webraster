import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { FinanceContractForm } from '../forms/FinanceContractForm'
import { useCreateFinanceContract } from '../hooks/useFinance'

export default function FinanceContractCreatePage() {
  const navigate = useNavigate()
  const create = useCreateFinanceContract()

  return (
    <Page>
      <PageHeader
        title="Novo contrato"
        description="Vincule cliente, plano e regras de cobrança."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contratos', to: '/finance/contracts' },
          { label: 'Novo' },
        ]}
      />
      <PageContent>
        <FinanceContractForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            const contract = await create.mutateAsync(payload)
            navigate(`/finance/contracts/${contract.id}`)
          }}
        />
      </PageContent>
    </Page>
  )
}
