import { useNavigate, useParams } from 'react-router'
import { Loading, Page, PageContent, PageHeader } from '@/shared/design-system'
import { ServiceOrderForm } from '../forms/ServiceOrderForm'
import { useServiceOrderQuery, useUpdateServiceOrder } from '../hooks/useServiceOrders'

export default function ServiceOrderEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const query = useServiceOrderQuery(id)
  const update = useUpdateServiceOrder(id!)

  if (query.isLoading) {
    return (
      <Page>
        <PageContent>
          <Loading />
        </PageContent>
      </Page>
    )
  }

  if (!query.data) {
    return (
      <Page>
        <PageHeader
          title="OS não encontrada"
          breadcrumb={[{ label: 'Ordens de serviço', to: '/service-orders' }]}
        />
      </Page>
    )
  }

  return (
    <Page>
      <PageHeader
        title={`Editar ${query.data.code}`}
        description="Atualize dados, técnico e agendamento."
        breadcrumb={[
          { label: 'Ordens de serviço', to: '/service-orders' },
          { label: query.data.code, to: `/service-orders/${query.data.id}` },
          { label: 'Editar' },
        ]}
      />
      <PageContent>
        <ServiceOrderForm
          mode="edit"
          initial={query.data}
          submitting={update.isPending}
          onSubmit={async (payload) => {
            await update.mutateAsync(payload)
            navigate(`/service-orders/${id}`)
          }}
        />
      </PageContent>
    </Page>
  )
}
