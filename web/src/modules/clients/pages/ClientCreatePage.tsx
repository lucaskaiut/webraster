import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ClientForm } from '../forms/ClientForm'
import { useCreateClient } from '../hooks/useClients'

export default function ClientCreatePage() {
  const navigate = useNavigate()
  const createClient = useCreateClient()

  return (
    <Page>
      <PageHeader
        title="Novo cliente"
        description="Cadastre um novo cliente na sua operação."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Clientes', to: '/clients' },
          { label: 'Novo cliente' },
        ]}
      />

      <PageContent>
        <ClientForm
          mode="create"
          submitting={createClient.isPending}
          onSubmit={async (payload) => {
            await createClient.mutateAsync(payload)
            navigate('/clients')
          }}
        />
      </PageContent>
    </Page>
  )
}
