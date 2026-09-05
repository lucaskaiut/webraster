import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { EquipmentForm } from '../forms/EquipmentForm'
import { useCreateEquipment } from '../hooks/useEquipments'

export default function EquipmentCreatePage() {
  const navigate = useNavigate()
  const createEquipment = useCreateEquipment()

  return (
    <Page>
      <PageHeader
        title="Novo equipamento"
        description="Cadastre um rastreador para associação à frota."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Equipamentos', to: '/equipments' },
          { label: 'Novo equipamento' },
        ]}
      />

      <PageContent>
        <EquipmentForm
          mode="create"
          submitting={createEquipment.isPending}
          onSubmit={async (payload) => {
            await createEquipment.mutateAsync(payload)
            navigate('/equipments')
          }}
        />
      </PageContent>
    </Page>
  )
}
