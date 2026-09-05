import { useNavigate, useParams } from 'react-router'
import { Loading, Page, PageContent, PageHeader } from '@/shared/design-system'
import { GeofenceForm } from '../forms/GeofenceForm'
import { useGeofenceQuery, useUpdateGeofence } from '../hooks/useGeofences'

export default function GeofenceEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const query = useGeofenceQuery(id)
  const update = useUpdateGeofence(id!)

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
        <PageHeader title="Geocerca não encontrada" breadcrumb={[{ label: 'Geocercas', to: '/geofences' }]} />
      </Page>
    )
  }

  const geofence = query.data

  return (
    <Page>
      <PageHeader
        title={geofence.name}
        description="Edite os dados e a geometria da cerca."
        breadcrumb={[
          { label: 'Geocercas', to: '/geofences' },
          { label: geofence.name },
        ]}
      />
      <PageContent>
        <GeofenceForm
          mode="edit"
          submitting={update.isPending}
          defaultValues={{
            client_id: geofence.client_id ?? '',
            name: geofence.name,
            description: geofence.description ?? '',
            type: geofence.type,
            is_active: geofence.is_active,
            center_latitude: geofence.center_latitude,
            center_longitude: geofence.center_longitude,
            radius_meters: geofence.radius_meters,
            geometry: geofence.geometry ?? [],
          }}
          onSubmit={async (payload) => {
            await update.mutateAsync(payload)
            navigate('/geofences')
          }}
        />
      </PageContent>
    </Page>
  )
}
