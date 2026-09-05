import { useNavigate, useParams } from 'react-router'
import { Cpu } from 'lucide-react'
import {
  ButtonLink,
  Card,
  CardContent,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { EquipmentForm } from '../forms/EquipmentForm'
import { useEquipmentQuery, useUpdateEquipment } from '../hooks/useEquipments'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
      </CardContent>
    </Card>
  )
}

export default function EquipmentEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useEquipmentQuery(id)
  const updateEquipment = useUpdateEquipment(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar equipamento"
        description={query.data ? `Atualize os dados de ${query.data.imei}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Equipamentos', to: '/equipments' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Cpu}
              title="Equipamento não encontrado"
              description="O equipamento pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/equipments" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <EquipmentForm
            mode="edit"
            defaultValues={{
              imei: query.data.imei,
              model: query.data.model ?? '',
              iccid: query.data.iccid ?? '',
              carrier: query.data.carrier ?? '',
              is_active: query.data.is_active,
            }}
            submitting={updateEquipment.isPending}
            onSubmit={async (payload) => {
              await updateEquipment.mutateAsync(payload)
              navigate('/equipments')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
