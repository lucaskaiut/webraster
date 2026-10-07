import { useNavigate, useSearchParams } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { DriverForm } from '../forms/DriverForm'
import { useCreateDriver } from '../hooks/useDrivers'

export default function DriverCreatePage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const createDriver = useCreateDriver()

  const fixedClientId = searchParams.get('client_id') ?? undefined
  const fixedVehicleId = searchParams.get('vehicle_id') ?? undefined

  return (
    <Page>
      <PageHeader
        title="Novo motorista"
        description="Cadastre um novo motorista vinculado a um cliente."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Motoristas', to: '/drivers' },
          { label: 'Novo motorista' },
        ]}
      />

      <PageContent>
        <DriverForm
          mode="create"
          fixedClientId={fixedClientId}
          fixedVehicleId={fixedVehicleId}
          submitting={createDriver.isPending}
          onSubmit={async (payload) => {
            await createDriver.mutateAsync(payload)
            navigate('/drivers')
          }}
        />
      </PageContent>
    </Page>
  )
}
