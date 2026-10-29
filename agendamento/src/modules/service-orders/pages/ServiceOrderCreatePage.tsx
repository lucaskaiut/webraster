import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ServiceOrderForm } from '../components/ServiceOrderForm'
import { useCreateServiceOrder } from '../hooks/useServiceOrders'

export default function ServiceOrderCreatePage() {
  const navigate = useNavigate()
  const create = useCreateServiceOrder()

  return (
    <Page>
      <PageHeader
        title="Nova ordem de serviço"
        description="Informe endereço e serviço, escolha o horário e anexe o documento."
        breadcrumb={[
          { label: 'Ordens de serviço', to: '/service-orders' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <ServiceOrderForm
          submitting={create.isPending}
          onSubmit={async (payload, attachments) => {
            const order = await create.mutateAsync({ payload, attachments })
            navigate(`/service-orders/${order.id}`)
          }}
        />
      </PageContent>
    </Page>
  )
}
