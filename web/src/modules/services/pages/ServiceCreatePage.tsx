import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ServiceForm } from '../forms/ServiceForm'
import { useCreateService } from '../hooks/useServices'

export default function ServiceCreatePage() {
  const navigate = useNavigate()
  const createService = useCreateService()

  return (
    <Page>
      <PageHeader
        title="Novo serviço"
        description="Defina o nome e o valor do serviço."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Serviços', to: '/services' },
          { label: 'Novo serviço' },
        ]}
      />

      <PageContent>
        <ServiceForm
          mode="create"
          submitting={createService.isPending}
          onSubmit={async (payload) => {
            await createService.mutateAsync(payload)
            navigate('/services')
          }}
        />
      </PageContent>
    </Page>
  )
}
