import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { ServiceOrderForm } from '../forms/ServiceOrderForm'
import { useCreateServiceOrder } from '../hooks/useServiceOrders'

export default function ServiceOrderCreatePage() {
  const navigate = useNavigate()
  const create = useCreateServiceOrder()

  return (
    <Page>
      <PageHeader
        title="Nova ordem de serviço"
        description="Defina tipo, cliente, técnico e agendamento."
        breadcrumb={[
          { label: 'Ordens de serviço', to: '/service-orders' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <ServiceOrderForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            const order = await create.mutateAsync(payload)
            navigate(`/service-orders/${order.id}`)
          }}
        />
      </PageContent>
    </Page>
  )
}
