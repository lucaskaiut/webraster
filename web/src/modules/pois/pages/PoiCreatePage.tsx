import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { PoiForm } from '../forms/PoiForm'
import { useCreatePoi } from '../hooks/usePois'

export default function PoiCreatePage() {
  const navigate = useNavigate()
  const create = useCreatePoi()

  return (
    <Page>
      <PageHeader
        title="Novo POI"
        description="Cadastre um ponto de interesse no mapa."
        breadcrumb={[
          { label: 'POIs', to: '/pois' },
          { label: 'Novo' },
        ]}
      />
      <PageContent>
        <PoiForm
          mode="create"
          submitting={create.isPending}
          onSubmit={async (payload) => {
            await create.mutateAsync(payload)
            navigate('/pois')
          }}
        />
      </PageContent>
    </Page>
  )
}
