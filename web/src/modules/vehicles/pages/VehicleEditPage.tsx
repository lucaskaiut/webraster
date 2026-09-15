import { useNavigate, useParams } from 'react-router'
import { Car } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  SearchSelectField,
  Skeleton,
  TextField,
} from '@/shared/design-system'
import { FormProvider, useForm } from 'react-hook-form'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { formatDateTime } from '@/shared/utils/format'
import { equipmentsService } from '@/modules/equipments/services/equipments.service'
import { VehicleForm } from '../forms/VehicleForm'
import {
  useInstallEquipment,
  useRemoveEquipment,
  useSwapEquipment,
  useUpdateVehicle,
  useVehicleHistoryQuery,
  useVehicleQuery,
} from '../hooks/useVehicles'

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

const eventLabel = {
  installation: 'Instalação',
  removal: 'Remoção',
  swap: 'Troca',
} as const

async function loadAvailableEquipments(search: string) {
  const response = await equipmentsService.list({
    search: search || undefined,
    available: true,
    per_page: 20,
  })

  return response.data.map((item) => ({
    value: item.id,
    label: `${item.imei}${item.model ? ` · ${item.model}` : ''}`,
  }))
}

export default function VehicleEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useVehicleQuery(id)
  const historyQuery = useVehicleHistoryQuery(id)
  const updateVehicle = useUpdateVehicle(id ?? '')
  const install = useInstallEquipment(id ?? '')
  const remove = useRemoveEquipment(id ?? '')
  const swap = useSwapEquipment(id ?? '')

  const assignmentForm = useForm({
    defaultValues: { equipment_id: '', notes: '' },
  })

  const vehicle = query.data
  const hasEquipment = Boolean(vehicle?.equipment)

  const submitAssignment = assignmentForm.handleSubmit(async (values) => {
    if (!values.equipment_id) return

    const payload = {
      equipment_id: values.equipment_id,
      notes: values.notes || null,
    }

    if (hasEquipment) {
      await swap.mutateAsync(payload)
    } else {
      await install.mutateAsync(payload)
    }

    assignmentForm.reset({ equipment_id: '', notes: '' })
  })

  return (
    <Page>
      <PageHeader
        title="Editar veículo"
        description={vehicle ? `Atualize os dados de ${vehicle.plate}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Veículos', to: '/vehicles' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={Car}
              title="Veículo não encontrado"
              description="O veículo pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/vehicles" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {vehicle && (
          <div className="space-y-6">
            <VehicleForm
              mode="edit"
              defaultValues={{
                client_id: vehicle.client_id,
                plate: vehicle.plate,
                chassis: vehicle.chassis ?? '',
                renavam: vehicle.renavam ?? '',
                brand: vehicle.brand ?? '',
                model: vehicle.model ?? '',
                color: vehicle.color ?? '',
                year: vehicle.year ? String(vehicle.year) : '',
                transmission: vehicle.transmission ?? '',
                odometer: vehicle.odometer ? String(vehicle.odometer) : '',
                max_speed_kmh: vehicle.max_speed_kmh ? String(vehicle.max_speed_kmh) : '',
                speed_hysteresis_percent: vehicle.speed_hysteresis_percent
                  ? String(vehicle.speed_hysteresis_percent)
                  : '3',
                speed_min_duration_seconds: vehicle.speed_min_duration_seconds
                  ? String(vehicle.speed_min_duration_seconds)
                  : '30',
                average_consumption: vehicle.average_consumption
                  ? String(vehicle.average_consumption)
                  : '',
                tank_capacity: vehicle.tank_capacity ? String(vehicle.tank_capacity) : '',
                crlv_file: vehicle.crlv_file ?? '',
                fipe_code: vehicle.fipe_code ?? '',
                fipe_model_year: vehicle.fipe_model_year ?? '',
                fipe_fuel: vehicle.fipe_fuel ?? '',
                fipe_reference_month: vehicle.fipe_reference_month ?? '',
                fipe_value: vehicle.fipe_value ?? '',
                fipe_model: vehicle.fipe_model ?? '',
                fipe_brand: vehicle.fipe_brand ?? '',
                fipe_score: vehicle.fipe_score ? String(vehicle.fipe_score) : '',
                is_active: vehicle.is_active,
              }}
              crlvFileUrl={vehicle.crlv_file_url}
              submitting={updateVehicle.isPending}
              onSubmit={async (payload) => {
                await updateVehicle.mutateAsync(payload)
                navigate('/vehicles')
              }}
            />

            <Card>
              <CardHeader
                title="Equipamento"
                description="Associe, troque ou remova o rastreador deste veículo."
              />
              <CardContent className="space-y-4">
                {vehicle.equipment ? (
                  <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2 px-4 py-3">
                    <div>
                      <p className="font-medium text-foreground">{vehicle.equipment.imei}</p>
                      <p className="text-sm text-muted">
                        {[vehicle.equipment.model, vehicle.equipment.carrier]
                          .filter(Boolean)
                          .join(' · ') || 'Equipamento instalado'}
                      </p>
                    </div>
                    <Can permission={Permission.VEHICLE_UPDATE}>
                      <Button
                        variant="secondary"
                        loading={remove.isPending}
                        onClick={() => remove.mutate({ notes: null })}
                      >
                        Remover
                      </Button>
                    </Can>
                  </div>
                ) : (
                  <p className="text-sm text-muted">Nenhum equipamento instalado neste veículo.</p>
                )}

                <Can permission={Permission.VEHICLE_UPDATE}>
                  <FormProvider {...assignmentForm}>
                    <form onSubmit={submitAssignment} className="space-y-4">
                      <SearchSelectField
                        name="equipment_id"
                        label={hasEquipment ? 'Novo equipamento' : 'Equipamento disponível'}
                        required
                        placeholder="Buscar por IMEI..."
                        emptyMessage="Nenhum equipamento disponível"
                        loadOptions={loadAvailableEquipments}
                      />
                      <TextField name="notes" label="Observações" />
                      <div className="flex justify-end">
                        <Button type="submit" loading={install.isPending || swap.isPending}>
                          {hasEquipment ? 'Trocar equipamento' : 'Instalar equipamento'}
                        </Button>
                      </div>
                    </form>
                  </FormProvider>
                </Can>
              </CardContent>
            </Card>

            <Card>
              <CardHeader
                title="Histórico de associação"
                description="Instalações, remoções e trocas registradas."
              />
              <CardContent>
                {historyQuery.isPending && <Skeleton className="h-20 w-full" />}
                {!historyQuery.isPending && (historyQuery.data?.length ?? 0) === 0 && (
                  <p className="text-sm text-muted">Nenhum evento registrado.</p>
                )}
                <ul className="space-y-3">
                  {(historyQuery.data ?? []).map((event) => (
                    <li
                      key={event.id}
                      className="flex flex-wrap items-start justify-between gap-2 border-b border-border pb-3 last:border-0"
                    >
                      <div>
                        <div className="flex items-center gap-2">
                          <Badge>{eventLabel[event.event]}</Badge>
                          <span className="text-sm text-foreground">
                            {event.equipment?.imei ?? event.equipment_id}
                          </span>
                        </div>
                        {event.previous_equipment && (
                          <p className="mt-1 text-sm text-muted">
                            Anterior: {event.previous_equipment.imei}
                          </p>
                        )}
                        {event.notes && <p className="mt-1 text-sm text-muted">{event.notes}</p>}
                      </div>
                      <span className="text-xs text-muted">{formatDateTime(event.occurred_at)}</span>
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          </div>
        )}
      </PageContent>
    </Page>
  )
}
