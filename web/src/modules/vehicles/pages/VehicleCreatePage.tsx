import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { VehicleForm } from '../forms/VehicleForm'
import { useCreateVehicle } from '../hooks/useVehicles'

export default function VehicleCreatePage() {
  const navigate = useNavigate()
  const createVehicle = useCreateVehicle()

  return (
    <Page>
      <PageHeader
        title="Novo veículo"
        description="Cadastre um veículo vinculado a um cliente."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Veículos', to: '/vehicles' },
          { label: 'Novo veículo' },
        ]}
      />

      <PageContent>
        <VehicleForm
          mode="create"
          submitting={createVehicle.isPending}
          onSubmit={async (payload) => {
            await createVehicle.mutateAsync(payload)
            navigate('/vehicles')
          }}
        />
      </PageContent>
    </Page>
  )
}
