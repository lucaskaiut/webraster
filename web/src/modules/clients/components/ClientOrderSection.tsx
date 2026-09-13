import { useForm, useFieldArray } from 'react-hook-form'
import { Plus, Trash2, Wrench } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Checkbox,
  EmptyState,
  Form,
  Section,
  SelectField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { formatCurrency } from '@/shared/utils/format'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { PERIODICITY_OPTIONS } from '@/modules/finance/lib/labels'
import { useServicesQuery } from '@/modules/services/hooks/useServices'
import { useVehiclesQuery } from '@/modules/vehicles/hooks/useVehicles'
import type { ClientOrder } from '@/shared/types/models'
import { clientOrderSchema, type ClientOrderFormValues } from '../schemas/client.schema'
import { useClientOrderQuery, useUpsertClientOrder } from '../hooks/useClients'

interface ClientOrderSectionProps {
  clientId: string
  onSaved?: () => Promise<void> | void
}

export function ClientOrderSection({ clientId, onSaved }: ClientOrderSectionProps) {
  const { can } = usePermissions()
  const canEdit = can(Permission.FINANCE_SUBSCRIPTION_CREATE)
  const orderQuery = useClientOrderQuery(clientId)
  const servicesQuery = useServicesQuery({ per_page: 100 })
  const vehiclesQuery = useVehiclesQuery({ client_id: clientId, per_page: 100 })
  const upsertOrder = useUpsertClientOrder(clientId)

  const services = servicesQuery.data?.data ?? []
  const vehicles = vehiclesQuery.data?.data ?? []
  const order = orderQuery.data

  if (orderQuery.isPending || servicesQuery.isPending || vehiclesQuery.isPending) {
    return (
      <Card>
        <CardContent>
          <p className="text-sm text-muted">Carregando pedido...</p>
        </CardContent>
      </Card>
    )
  }

  if (services.length === 0) {
    return (
      <Card>
        <CardContent>
          <EmptyState
            icon={Wrench}
            title="Nenhum serviço cadastrado"
            description="Cadastre os serviços e valores antes de montar o pedido do cliente."
            action={
              <ButtonLink to="/services/create" variant="secondary">
                Cadastrar serviço
              </ButtonLink>
            }
          />
        </CardContent>
      </Card>
    )
  }

  if (vehicles.length === 0) {
    return (
      <Card>
        <CardContent>
          <EmptyState
            icon={Wrench}
            title="Nenhum veículo deste cliente"
            description="Cadastre os veículos na etapa anterior para vincular aos serviços."
          />
        </CardContent>
      </Card>
    )
  }

  return (
    <ClientOrderForm
      order={order ?? null}
      services={services}
      vehicles={vehicles}
      submitting={upsertOrder.isPending}
      canEdit={canEdit}
      onSubmit={async (values) => {
        await upsertOrder.mutateAsync(values)
        await onSaved?.()
      }}
    />
  )
}

function ClientOrderForm({
  order,
  services,
  vehicles,
  submitting,
  canEdit,
  onSubmit,
}: {
  order: ClientOrder | null
  services: Array<{ id: string; name: string; amount_cents: number; amount: string }>
  vehicles: Array<{ id: string; plate: string; brand: string | null; model: string | null }>
  submitting: boolean
  canEdit: boolean
  onSubmit: (payload: ClientOrderFormValues) => Promise<unknown>
}) {
  const form = useForm<ClientOrderFormValues>({
    resolver: formResolver<ClientOrderFormValues>(clientOrderSchema),
    defaultValues: {
      due_day: order?.due_day ?? 10,
      periodicity: order?.periodicity ?? 'monthly',
      items:
        order?.items.map((item) => ({
          service_id: item.service_id,
          vehicle_ids: item.vehicles.map((vehicle) => vehicle.id),
        })) ?? [{ service_id: '', vehicle_ids: [] }],
    },
  })

  const items = useFieldArray({ control: form.control, name: 'items' })
  const watched = form.watch()

  const serviceById = new Map(services.map((service) => [service.id, service]))

  const lines = (watched.items ?? []).map((item) => {
    const service = serviceById.get(item.service_id)
    const quantity = item.vehicle_ids?.length ?? 0
    const unit = service?.amount_cents ?? 0
    return {
      name: service?.name ?? 'Serviço',
      unit,
      quantity,
      total: unit * quantity,
    }
  })
  const grandTotal = lines.reduce((sum, line) => sum + line.total, 0)

  const serviceOptions = services.map((service) => ({
    value: service.id,
    label: `${service.name} · ${formatCurrency(service.amount)}`,
  }))

  const handleSubmit = async (values: ClientOrderFormValues) => {
    try {
      await onSubmit(values)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section
            title="Pedido"
            description="Cada serviço pode ter veículos diferentes. O valor do contrato é serviço × quantidade de carros."
          >
            <div className="space-y-6">
              {items.fields.map((field, index) => {
                const line = lines[index]
                const selectedServiceIds = (watched.items ?? [])
                  .map((item, itemIndex) => (itemIndex === index ? null : item.service_id))
                  .filter(Boolean)
                const options = serviceOptions.filter(
                  (option) =>
                    option.value === watched.items?.[index]?.service_id ||
                    !selectedServiceIds.includes(option.value),
                )

                return (
                  <div key={field.id} className="space-y-4 rounded-xl border border-border/70 p-4">
                    <div className="flex items-start justify-between gap-3">
                      <SelectField
                        name={`items.${index}.service_id`}
                        label="Serviço"
                        required
                        placeholder="Selecione..."
                        options={options}
                        className="min-w-0 flex-1"
                      />
                      {items.fields.length > 1 && canEdit && (
                        <Button
                          type="button"
                          variant="ghost"
                          className="mt-7 text-danger hover:bg-danger-soft hover:text-danger"
                          onClick={() => items.remove(index)}
                          aria-label="Remover serviço"
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      )}
                    </div>

                    <fieldset>
                      <legend className="mb-2 text-sm font-medium text-foreground">Veículos</legend>
                      <div className="grid gap-2 sm:grid-cols-2">
                        {vehicles.map((vehicle) => {
                          const checked = (watched.items?.[index]?.vehicle_ids ?? []).includes(vehicle.id)
                          const label = [vehicle.plate, vehicle.brand, vehicle.model]
                            .filter(Boolean)
                            .join(' · ')

                          return (
                            <Checkbox
                              key={vehicle.id}
                              label={label}
                              checked={checked}
                              disabled={!canEdit}
                              onChange={(event) => {
                                const current = form.getValues(`items.${index}.vehicle_ids`) ?? []
                                const next = event.target.checked
                                  ? [...current, vehicle.id]
                                  : current.filter((id) => id !== vehicle.id)
                                form.setValue(`items.${index}.vehicle_ids`, next, {
                                  shouldDirty: true,
                                  shouldValidate: true,
                                })
                              }}
                            />
                          )
                        })}
                      </div>
                      {form.formState.errors.items?.[index]?.vehicle_ids?.message && (
                        <p className="mt-2 text-[13px] text-danger">
                          {form.formState.errors.items[index]?.vehicle_ids?.message}
                        </p>
                      )}
                    </fieldset>

                    <p className="text-sm text-muted">
                      {formatCurrency(line.unit / 100)} × {line.quantity}{' '}
                      {line.quantity === 1 ? 'carro' : 'carros'} ={' '}
                      <span className="font-medium text-foreground">{formatCurrency(line.total / 100)}</span>
                    </p>
                  </div>
                )
              })}

              {canEdit && (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => items.append({ service_id: '', vehicle_ids: [] })}
                  disabled={items.fields.length >= services.length}
                >
                  <Plus className="size-4" />
                  Adicionar serviço
                </Button>
              )}
            </div>
          </Section>

          <Section title="Recorrência" description="As cobranças usam o valor total deste pedido.">
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectField
                name="periodicity"
                label="Periodicidade"
                options={PERIODICITY_OPTIONS}
                required
                disabled={!canEdit}
              />
              <TextField
                name="due_day"
                label="Dia de vencimento"
                type="number"
                min={1}
                max={28}
                hint="Dia do mês (1–28) usado nas cobranças."
                required
                disabled={!canEdit}
              />
            </div>
            <p className="text-base font-semibold text-foreground">
              Total do contrato: {formatCurrency(grandTotal / 100)}
            </p>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {canEdit && (
              <Button type="submit" loading={submitting}>
                Salvar pedido
              </Button>
            )}
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
