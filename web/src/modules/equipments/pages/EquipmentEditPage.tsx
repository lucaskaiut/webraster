import { useNavigate, useParams } from 'react-router'
import { Cpu } from 'lucide-react'
import {
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { VehicleCommandsPanel } from '@/modules/tracking/components/VehicleCommandsPanel'
import { DeviceCommandHistory } from '@/modules/tracking/components/DeviceCommandHistory'
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
  const { can } = usePermissions()

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
          <>
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

            {can(Permission.DEVICE_COMMANDS_SEND) && (
              <Card>
                <CardHeader
                  title="Comandos"
                  description="Envie comandos para este rastreador via Traccar."
                />
                <CardContent>
                  <VehicleCommandsPanel deviceId={query.data.id} />
                </CardContent>
              </Card>
            )}

            {can(Permission.DEVICE_COMMANDS_SEND) && (
              <Card>
                <CardHeader
                  title="Histórico de comandos"
                  description="Últimos comandos enviados a este rastreador."
                />
                <CardContent>
                  <DeviceCommandHistory deviceId={query.data.id} />
                </CardContent>
              </Card>
            )}
          </>
        )}
      </PageContent>
    </Page>
  )
}
