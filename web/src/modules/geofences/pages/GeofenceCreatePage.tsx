import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { GeofenceForm } from '../forms/GeofenceForm'
import { useCreateGeofence } from '../hooks/useGeofences'

export default function GeofenceCreatePage() {
  const navigate = useNavigate()
  const create = useCreateGeofence()

  return (
    <Page>
      <PageHeader
        title="Nova geocerca"
        description="Desenhe um círculo ou polígono no mapa."
        breadcrumb={[
          { label: 'Geocercas', to: '/geofences' },
          { label: 'Nova' },
        ]}
      />
      <PageContent>
        <GeofenceForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/geofences')
          }}
        />
      </PageContent>
    </Page>
  )
}
