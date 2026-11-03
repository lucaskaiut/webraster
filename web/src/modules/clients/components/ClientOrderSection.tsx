import { useForm } from 'react-hook-form'
import { Package, Wrench } from 'lucide-react'
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
import { periodicityLabel } from '@/modules/finance/lib/labels'
import { useFinancePlansQuery } from '@/modules/finance/hooks/useFinance'
import { useVehiclesQuery } from '@/modules/vehicles/hooks/useVehicles'
import type { ClientOrder, FinancePlan } from '@/shared/types/models'
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
  const plansQuery = useFinancePlansQuery({ per_page: 100 })
  const vehiclesQuery = useVehiclesQuery({ client_id: clientId, per_page: 100 })
  const upsertOrder = useUpsertClientOrder(clientId)

  const order = orderQuery.data
  const vehicles = vehiclesQuery.data?.data ?? []
  // Planos inativos só aparecem se já forem o plano atual do pedido.
  const plans = (plansQuery.data?.data ?? []).filter(
    (plan) => plan.is_active || plan.id === order?.plan_id,
  )

  if (orderQuery.isPending || plansQuery.isPending || vehiclesQuery.isPending) {
    return (
      <Card>
        <CardContent>
          <p className="text-sm text-muted">Carregando pedido...</p>
        </CardContent>
      </Card>
    )
  }

  if (plans.length === 0) {
    return (
      <Card>
        <CardContent>
          <EmptyState
            icon={Package}
            title="Nenhum plano cadastrado"
            description="Cadastre os planos e valores antes de montar o pedido do cliente."
            action={
              <ButtonLink to="/finance/plans/create" variant="secondary">
                Cadastrar plano
              </ButtonLink>
            }
          />
        </CardContent>
      </Card>
    )
  }

  return (
    <ClientOrderForm
      order={order ?? null}
      plans={plans}
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
  plans,
  vehicles,
  submitting,
  canEdit,
  onSubmit,
}: {
  order: ClientOrder | null
  plans: FinancePlan[]
  vehicles: Array<{ id: string; plate: string; brand: string | null; model: string | null }>
  submitting: boolean
  canEdit: boolean
  onSubmit: (payload: ClientOrderFormValues) => Promise<unknown>
}) {
  const form = useForm<ClientOrderFormValues>({
    resolver: formResolver<ClientOrderFormValues>(clientOrderSchema),
    defaultValues: {
      due_day: order?.due_day ?? 10,
      plan_id: order?.plan_id ?? '',
      vehicle_ids: order?.vehicle_ids ?? [],
    },
  })

  const planId = form.watch('plan_id')
  const vehicleIds = form.watch('vehicle_ids') ?? []
  const plan = plans.find((item) => item.id === planId)
  const perVehicle = plan?.pricing_type === 'per_vehicle'
  const quantity = vehicleIds.length
  const total = plan ? (perVehicle ? plan.amount_cents * quantity : plan.amount_cents) : 0

  const planOptions = plans.map((item) => ({
    value: item.id,
    label:
      item.pricing_type === 'per_vehicle'
        ? `${item.name} · ${formatCurrency(item.amount)} por veículo`
        : `${item.name} · ${formatCurrency(item.amount)}`,
  }))

  const handleSubmit = async (values: ClientOrderFormValues) => {
    if (perVehicle && values.vehicle_ids.length === 0) {
      form.setError('vehicle_ids', { message: 'Selecione ao menos um veículo' })
      return
    }

    try {
      await onSubmit({ ...values, vehicle_ids: perVehicle ? values.vehicle_ids : [] })
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
            title="Plano"
            description="O plano define o valor do contrato e das cobranças recorrentes do cliente."
          >
            <div className="space-y-6">
              <SelectField
                name="plan_id"
                label="Plano"
                required
                placeholder="Selecione..."
                options={planOptions}
                disabled={!canEdit}
              />

              {order && !order.plan_id && order.items.length > 0 && (
                <p className="text-sm text-muted">
                  Este pedido foi criado com serviços ({order.items.map((item) => item.name).join(', ')}).
                  Selecione um plano para substituí-lo.
                </p>
              )}

              {perVehicle && (
                <fieldset>
                  <legend className="mb-2 text-sm font-medium text-foreground">Veículos</legend>
                  {vehicles.length === 0 ? (
                    <EmptyState
                      icon={Wrench}
                      title="Nenhum veículo deste cliente"
                      description="Cadastre os veículos na etapa anterior para vincular ao plano."
                    />
                  ) : (
                    <div className="grid gap-2 sm:grid-cols-2">
                      {vehicles.map((vehicle) => {
                        const checked = vehicleIds.includes(vehicle.id)
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
                              const current = form.getValues('vehicle_ids') ?? []
                              const next = event.target.checked
                                ? [...current, vehicle.id]
                                : current.filter((id) => id !== vehicle.id)
                              form.setValue('vehicle_ids', next, {
                                shouldDirty: true,
                                shouldValidate: true,
                              })
                              if (next.length > 0) form.clearErrors('vehicle_ids')
                            }}
                          />
                        )
                      })}
                    </div>
                  )}
                  {form.formState.errors.vehicle_ids?.message && (
                    <p className="mt-2 text-[13px] text-danger">
                      {form.formState.errors.vehicle_ids.message}
                    </p>
                  )}
                </fieldset>
              )}

              {plan && (
                <p className="text-sm text-muted">
                  {perVehicle ? (
                    <>
                      {formatCurrency(plan.amount_cents / 100)} × {quantity}{' '}
                      {quantity === 1 ? 'veículo' : 'veículos'} ={' '}
                    </>
                  ) : (
                    <>Valor fixo: </>
                  )}
                  <span className="font-medium text-foreground">{formatCurrency(total / 100)}</span>
                </p>
              )}
            </div>
          </Section>

          <Section title="Recorrência" description="As cobranças usam o valor total deste pedido.">
            <div className="grid gap-4 sm:grid-cols-2">
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
              {plan && (
                <div className="flex flex-col justify-center">
                  <span className="text-sm font-medium text-foreground">Periodicidade</span>
                  <span className="text-sm text-muted">
                    {plan.periodicity_label ?? periodicityLabel(plan.periodicity)} (definida no plano)
                  </span>
                </div>
              )}
            </div>
            <p className="text-base font-semibold text-foreground">
              Total do contrato: {formatCurrency(total / 100)}
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
