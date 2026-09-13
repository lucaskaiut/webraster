import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ContractForm } from '../forms/ContractForm'
import { useCreateContract } from '../hooks/useContracts'

export default function ContractCreatePage() {
  const navigate = useNavigate()
  const createContract = useCreateContract()

  return (
    <Page>
      <PageHeader
        title="Novo contrato"
        description="Defina o nome e o texto do contrato com variáveis do sistema."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contratos', to: '/contracts' },
          { label: 'Novo contrato' },
        ]}
      />

      <PageContent>
        <ContractForm
          mode="create"
          submitting={createContract.isPending}
          onSubmit={async (payload) => {
            await createContract.mutateAsync(payload)
            navigate('/contracts')
          }}
        />
      </PageContent>
    </Page>
  )
}
