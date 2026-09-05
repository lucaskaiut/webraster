import { useNavigate, useParams } from 'react-router'
import { Loading, Page, PageContent, PageHeader } from '@/shared/design-system'
import { PoiForm } from '../forms/PoiForm'
import { usePoiQuery, useUpdatePoi } from '../hooks/usePois'

export default function PoiEditPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const query = usePoiQuery(id)
  const update = useUpdatePoi(id!)

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
        <PageHeader title="POI não encontrado" breadcrumb={[{ label: 'POIs', to: '/pois' }]} />
      </Page>
    )
  }

  const poi = query.data

  return (
    <Page>
      <PageHeader
        title={poi.name}
        description="Edite os dados e a localização."
        breadcrumb={[
          { label: 'POIs', to: '/pois' },
          { label: poi.name },
        ]}
      />
      <PageContent>
        <PoiForm
          mode="edit"
          submitting={update.isPending}
          defaultValues={{
            client_id: poi.client_id ?? '',
            poi_category_id: poi.category_id ?? '',
            name: poi.name,
            description: poi.description ?? '',
            latitude: poi.latitude,
            longitude: poi.longitude,
            address: poi.address ?? '',
            is_active: poi.is_active,
          }}
          onSubmit={async (payload) => {
            await update.mutateAsync(payload)
            navigate('/pois')
          }}
        />
      </PageContent>
    </Page>
  )
}
