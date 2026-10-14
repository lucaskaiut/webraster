import { useEffect, useMemo, useState } from 'react'
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
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { VehicleCommandsPanel } from '@/modules/tracking/components/VehicleCommandsPanel'
import { DeviceCommandHistory } from '@/modules/tracking/components/DeviceCommandHistory'
import { VehicleStatusIndicators } from '@/modules/tracking/components/VehicleStatusIndicators'
import { useTrackingLiveEquipmentsQuery, useTrackingLiveQuery } from '@/modules/tracking/hooks/useTracking'
import type { TrackingLiveVehicle } from '@/shared/types/models'
import { EquipmentForm } from '../forms/EquipmentForm'
import { useEquipmentQuery, useUpdateEquipment } from '../hooks/useEquipments'
import { equipmentLiveAsVehicle } from '../lib/live'

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

  const canTrack = can(Permission.TRACKING_READ)
  const liveQuery = useTrackingLiveQuery(canTrack)
  const equipmentLiveQuery = useTrackingLiveEquipmentsQuery(canTrack)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (!canTrack) return

    const timer = window.setInterval(() => setNow(Date.now()), 30_000)

    return () => window.clearInterval(timer)
  }, [canTrack])

  const live = useMemo<TrackingLiveVehicle | null>(() => {
    for (const item of liveQuery.data ?? []) {
      if (item.equipment?.id === id) return item
    }

    const stock = (equipmentLiveQuery.data ?? []).find((item) => item.id === id)

    return stock ? equipmentLiveAsVehicle(stock) : null
  }, [liveQuery.data, equipmentLiveQuery.data, id])

  return (
    <Page>
      <PageHeader
        title="Editar equipamento"
        description={
          query.data
            ? query.data.imei
              ? `Atualize os dados de ${query.data.imei}.`
              : 'Atualize os dados do equipamento.'
            : undefined
        }
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
                imei: query.data.imei ?? '',
                model: query.data.model ?? '',
                line_number: query.data.line_number ?? '',
                carrier: query.data.carrier ?? '',
                is_active: query.data.is_active,
              }}
              submitting={updateEquipment.isPending}
              onSubmit={async (payload) => {
                await updateEquipment.mutateAsync(payload)
                navigate('/equipments')
              }}
            />

            {canTrack && (
              <Card>
                <CardHeader
                  title="Posição e indicadores"
                  description="Última posição conhecida do equipamento, mesmo antes do vínculo com um veículo."
                />
                <CardContent>
                  {!live ? (
                    <p className="text-sm text-muted">
                      Nenhuma posição recebida ainda para este equipamento.
                    </p>
                  ) : (
                    <div className="space-y-3">
                      <p className="text-[13px] text-muted">
                        Última posição:{' '}
                        <span className="font-medium text-foreground">
                          {formatDateTimeWithSeconds(live.position?.recorded_at)}
                        </span>
                      </p>
                      <VehicleStatusIndicators vehicle={live} now={now} />
                    </div>
                  )}
                </CardContent>
              </Card>
            )}

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
