import { useMemo, useState, type ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2, Wrench } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Field,
  Form,
  ImageUploader,
  Input,
  SearchSelect,
  SearchSelectField,
  SelectField,
  TextareaField,
  type ImageValue,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { formatCurrency } from '@/shared/utils/format'
import { formatDisplayDateTime, parseLocalDateTime, toLocalDateTimeValue } from '@/shared/utils/date'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { clientsService } from '@/modules/clients/services/clients.service'
import { vehiclesService } from '@/modules/vehicles/services/vehicles.service'
import { equipmentsService } from '@/modules/equipments/services/equipments.service'
import { usersService } from '@/modules/users/services/users.service'
import { servicesService } from '@/modules/services/services/services.service'
import { ServiceOrderSchedulingPanel } from './ServiceOrderSchedulingPanel'
import { formatMinutes } from '../lib/duration'
import type { ServiceOrder } from '@/shared/types/models'
import type { ServiceOrderItemPayload, ServiceOrderPayload } from '../services/service-orders.service'
import { serviceOrderSchema, type ServiceOrderFormValues } from '../schemas/service-order.schema'
import {
  SERVICE_ORDER_INITIAL_STATUSES,
  SERVICE_ORDER_PRIORITY_OPTIONS,
  SERVICE_ORDER_STATUS_TRANSITIONS,
  priorityLabel,
  serviceOrderStatusOptions,
  statusLabel,
} from '../lib/labels'
import {
  centsToReaisInput,
  itemLineTotalCents,
  itemQuantity,
  itemsTotalCents as calculateItemsTotal,
  reaisInputToCents,
  validateServiceOrderItems,
  type ServiceOrderItemDraft,
} from '../lib/items'

function toFormDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const date = parseLocalDateTime(value)
  if (!date) return ''
  return toLocalDateTimeValue(date)
}

function fromFormDateTime(value: string): string | null {
  if (!value) return null
  const date = parseLocalDateTime(value)
  if (!date) return null
  return date.toISOString()
}

interface ServiceOption extends SearchSelectOption {
  amount_cents: number
}

async function loadServiceOptions(search: string): Promise<ServiceOption[]> {
  const response = await servicesService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((service) => ({
    value: service.id,
    label: service.name,
    description: [
      formatCurrency(service.amount_cents / 100),
      service.estimated_minutes ? formatMinutes(service.estimated_minutes) : null,
    ]
      .filter(Boolean)
      .join(' · '),
    amount_cents: service.amount_cents,
  }))
}

async function loadClientOptions(search: string) {
  const response = await clientsService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((client) => ({ value: client.id, label: client.name }))
}

async function resolveClientLabel(value: string) {
  try {
    const client = await clientsService.get(value)
    return { value: client.id, label: client.name }
  } catch {
    return null
  }
}

async function loadVehicleOptions(search: string, clientId?: string) {
  const response = await vehiclesService.list({
    search: search || undefined,
    client_id: clientId || undefined,
    per_page: 20,
  })
  return response.data.map((vehicle) => ({
    value: vehicle.id,
    label: `${vehicle.plate}${vehicle.client?.name ? ` · ${vehicle.client.name}` : ''}`,
  }))
}

async function resolveVehicleLabel(value: string) {
  try {
    const vehicle = await vehiclesService.get(value)
    return { value: vehicle.id, label: vehicle.plate }
  } catch {
    return null
  }
}

async function loadEquipmentOptions(search: string, technicianId?: string) {
  const response = await equipmentsService.list({
    search: search || undefined,
    per_page: 20,
    available: true,
    technician_id: technicianId || undefined,
  })

  return response.data.map((item) => ({
    value: item.id,
    label: [item.imei, item.model].filter(Boolean).join(' · ') || 'Equipamento',
    description: item.stock?.name ?? (item.is_assigned ? undefined : 'Sem estoque'),
  }))
}

async function resolveEquipmentLabel(value: string) {
  try {
    const item = await equipmentsService.get(value)
    return { value: item.id, label: item.imei ?? 'Equipamento' }
  } catch {
    return null
  }
}

async function loadTechnicianOptions(search: string) {
  const response = await usersService.list({
    search: search || undefined,
    type: 'technician',
    technician_active: true,
    per_page: 20,
  })

  return response.data.map((user) => ({
    value: user.id,
    label: user.name,
  }))
}

async function resolveTechnicianLabel(value: string) {
  try {
    const user = await usersService.get(value)
    return { value: user.id, label: user.name }
  } catch {
    return null
  }
}

interface FormItemRow extends ServiceOrderItemDraft {
  key: string
}

function rowFromOrderItem(item: NonNullable<ServiceOrder['items']>[number]): FormItemRow {
  return {
    key: item.id,
    service_id: item.service_id ?? '',
    name: item.name,
    quantity: String(item.quantity),
    amount: centsToReaisInput(item.unit_amount_cents),
    missing: !item.service_id,
  }
}

function FormStep({
  step,
  title,
  description,
  actions,
  children,
}: {
  step: number
  title: string
  description?: string
  actions?: ReactNode
  children: ReactNode
}) {
  const titleId = `service-order-step-${step}`

  return (
    <section aria-labelledby={titleId} className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-3">
          <span
            aria-hidden="true"
            className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary">
            {step}
          </span>
          <div className="min-w-0">
            <h2 id={titleId} className="text-base font-semibold text-foreground">
              {title}
            </h2>
            {description && (
              <p className="mt-0.5 max-w-3xl text-[13px] text-muted">{description}</p>
            )}
          </div>
        </div>
        {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
      </div>
      {children}
    </section>
  )
}

export interface ServiceOrderFormDefaultItem {
  service_id: string
  name: string
  quantity: number
  unit_amount_cents: number
}

export interface ServiceOrderFormDefaults {
  client_id?: string
  vehicle_id?: string
  description?: string
  items?: ServiceOrderFormDefaultItem[]
}

interface ServiceOrderFormProps {
  mode: 'create' | 'edit'
  initial?: ServiceOrder | null
  /** Valores sugeridos ao criar uma OS (ignorados quando `initial` é informado). */
  defaults?: ServiceOrderFormDefaults
  lockClient?: boolean
  submitting: boolean
  submitLabel?: string
  cancelLabel?: string
  /** Sem `onCancel`, o botão de cancelar volta para a lista de OS. */
  onCancel?: () => void
  /** Conteúdo extra exibido antes do resumo de conclusão. */
  footer?: ReactNode
  onSubmit: (payload: ServiceOrderPayload) => Promise<unknown>
}

export function ServiceOrderForm({
  mode,
  initial,
  defaults,
  lockClient = false,
  submitting,
  submitLabel,
  cancelLabel = 'Cancelar',
  onCancel,
  footer,
  onSubmit,
}: ServiceOrderFormProps) {
  const { can } = usePermissions()
  const canChangeStatus = can(Permission.SERVICE_ORDER_CHANGE_STATUS)

  const form = useForm<ServiceOrderFormValues>({
    resolver: zodResolver(serviceOrderSchema),
    defaultValues: {
      priority: initial?.priority ?? 'normal',
      status: initial?.status ?? 'open',
      client_id: initial?.client_id ?? defaults?.client_id ?? '',
      vehicle_id: initial?.vehicle_id ?? defaults?.vehicle_id ?? '',
      equipment_id: initial?.equipment_id ?? '',
      technician_id: initial?.technician_id ?? '',
      scheduled_start_at: toFormDateTime(initial?.scheduled_start_at),
      scheduled_end_at: toFormDateTime(initial?.scheduled_end_at),
      address: initial?.address ?? '',
      estimated_duration_minutes: initial?.estimated_duration_minutes
        ? String(initial.estimated_duration_minutes)
        : '',
      description: initial?.description ?? defaults?.description ?? '',
      notes: initial?.notes ?? '',
      installation_location: initial?.installation_location ?? '',
      cancellation_reason: initial?.cancellation_reason ?? '',
      ignore_schedule_conflict: false,
    },
  })

  const clientId = useWatch({ control: form.control, name: 'client_id' })
  const technicianId = useWatch({ control: form.control, name: 'technician_id' })
  const statusValue = useWatch({ control: form.control, name: 'status' })
  const priorityValue = useWatch({ control: form.control, name: 'priority' })
  const scheduledStartAt = useWatch({ control: form.control, name: 'scheduled_start_at' })
  const scheduledEndAt = useWatch({ control: form.control, name: 'scheduled_end_at' })

  const [items, setItems] = useState<FormItemRow[]>(() => {
    if (initial) {
      return (initial.items ?? []).map(rowFromOrderItem)
    }

    return (defaults?.items ?? []).map((item) => ({
      key: `new-${crypto.randomUUID()}`,
      service_id: item.service_id,
      name: item.name,
      quantity: String(item.quantity),
      amount: centsToReaisInput(item.unit_amount_cents),
    }))
  })
  const [itemsError, setItemsError] = useState<string | null>(null)
  const [installationPhoto, setInstallationPhoto] = useState<ImageValue | null>(() =>
    initial?.installation_location_photo_url
      ? {
          url: initial.installation_location_photo_url,
          path: initial.installation_location_photo_path ?? undefined,
          alt: 'Local de instalação',
        }
      : null,
  )

  const showStatusField = mode === 'create' || canChangeStatus

  const statusOptions = useMemo(() => {
    if (mode === 'create') {
      return serviceOrderStatusOptions(SERVICE_ORDER_INITIAL_STATUSES)
    }

    return serviceOrderStatusOptions(SERVICE_ORDER_STATUS_TRANSITIONS[initial?.status ?? 'open'])
  }, [mode, initial])

  const itemsTotalCents = useMemo(() => calculateItemsTotal(items), [items])
  const activeItemsCount = items.filter((item) => !item.missing).length
  const scheduleSummary = scheduledStartAt
    ? `${formatDisplayDateTime(scheduledStartAt)}${
        scheduledEndAt ? ` – ${formatDisplayDateTime(scheduledEndAt)}` : ''
      }`
    : 'Sem agendamento'
  const formHasErrors =
    Object.keys(form.formState.errors).length > 0 || Boolean(itemsError)

  const updateItem = (key: string, patch: Partial<FormItemRow>) => {
    setItemsError(null)
    setItems((current) => current.map((item) => (item.key === key ? { ...item, ...patch } : item)))
  }

  const addItem = () => {
    setItemsError(null)
    setItems((current) => [
      ...current,
      {
        key: `new-${crypto.randomUUID()}`,
        service_id: '',
        name: '',
        quantity: '1',
        amount: '0.00',
      },
    ])
  }

  const removeItem = (key: string) => {
    setItemsError(null)
    setItems((current) => current.filter((item) => item.key !== key))
  }

  const handleSubmit = async (values: ServiceOrderFormValues) => {
    const validationError = validateServiceOrderItems(items)
    if (validationError) {
      setItemsError(validationError)
      return
    }

    const itemsPayload: ServiceOrderItemPayload[] = items
      .filter((item) => !item.missing)
      .map((item) => ({
        service_id: item.service_id,
        quantity: itemQuantity(item.quantity),
        unit_amount_cents: reaisInputToCents(item.amount),
      }))

    setItemsError(null)

    const payload: ServiceOrderPayload = {
      priority: values.priority,
      client_id: values.client_id,
      vehicle_id: values.vehicle_id || null,
      equipment_id: values.equipment_id || null,
      technician_id: values.technician_id || null,
      scheduled_start_at: fromFormDateTime(values.scheduled_start_at ?? ''),
      scheduled_end_at: fromFormDateTime(values.scheduled_end_at ?? ''),
      address: values.address?.trim() || null,
      estimated_duration_minutes: values.estimated_duration_minutes
        ? Number(values.estimated_duration_minutes)
        : null,
      description: values.description || null,
      notes: values.notes || null,
      installation_location: values.installation_location?.trim() || null,
      installation_location_photo_path: installationPhoto?.path ?? null,
      items: itemsPayload,
      ignore_schedule_conflict: values.ignore_schedule_conflict ?? false,
    }

    if (showStatusField) {
      payload.status = values.status
      payload.cancellation_reason =
        values.status === 'cancelled' ? values.cancellation_reason || null : null
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        const itemError = Object.entries(error.fieldErrors).find(([field]) =>
          field.startsWith('items'),
        )

        if (itemError) {
          setItemsError(itemError[1]?.[0] ?? 'Revise os serviços informados.')
        }

        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-9">
          <FormStep
            step={1}
            title="Dados essenciais da OS"
            description="Identifique o atendimento e defina quem executa. Campos com * são obrigatórios.">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <SearchSelectField
                name="client_id"
                label="Cliente"
                required
                className="sm:col-span-2"
                disabled={lockClient}
                placeholder="Buscar cliente..."
                emptyMessage="Nenhum cliente encontrado"
                loadOptions={loadClientOptions}
                resolveLabel={resolveClientLabel}
                onSelectOption={() => {
                  form.setValue('vehicle_id', '')
                  form.setValue('address', '')
                }}
              />
              <SearchSelectField
                name="vehicle_id"
                label="Veículo"
                hint="Opcional · filtrado pelo cliente"
                placeholder="Buscar veículo..."
                emptyMessage="Nenhum veículo encontrado"
                loadOptions={(search) => loadVehicleOptions(search, clientId)}
                resolveLabel={resolveVehicleLabel}
              />
              <SearchSelectField
                name="equipment_id"
                label="Dispositivo"
                hint={
                  technicianId
                    ? 'Opcional · disponibilidade conforme o estoque do técnico'
                    : 'Opcional · selecione o técnico para filtrar pelo estoque'
                }
                placeholder="Buscar equipamento..."
                emptyMessage={
                  technicianId
                    ? 'Nenhum equipamento disponível para este técnico'
                    : 'Nenhum equipamento disponível'
                }
                loadOptions={(search) => loadEquipmentOptions(search, technicianId)}
                resolveLabel={resolveEquipmentLabel}
              />
              <SearchSelectField
                name="technician_id"
                label="Técnico responsável"
                hint="Opcional · agenda, deslocamento e estoque de equipamentos"
                className="sm:col-span-2"
                placeholder="Buscar técnico..."
                emptyMessage="Nenhum técnico ativo encontrado"
                loadOptions={loadTechnicianOptions}
                resolveLabel={resolveTechnicianLabel}
                onSelectOption={() => form.setValue('equipment_id', '')}
              />
              <SelectField
                name="priority"
                label="Prioridade"
                required
                options={SERVICE_ORDER_PRIORITY_OPTIONS}
              />
              {showStatusField && (
                <SelectField
                  name="status"
                  label="Situação"
                  required
                  options={statusOptions}
                  hint={
                    mode === 'edit'
                      ? 'A situação no kanban segue as transições permitidas. Ao concluir, o equipamento é instalado no veículo quando ambos estiverem definidos.'
                      : 'A OS entra direto no fluxo com a situação escolhida.'
                  }
                />
              )}
              {showStatusField && statusValue === 'cancelled' && (
                <TextareaField
                  name="cancellation_reason"
                  label="Motivo do cancelamento"
                  required
                  className="sm:col-span-2 xl:col-span-4"
                  placeholder="Descreva o motivo do cancelamento"
                />
              )}
            </div>
          </FormStep>

          <FormStep
            step={2}
            title="Serviços e valores"
            description="Selecione os serviços do catálogo e ajuste quantidade e valor. A soma dos tempos estimados define a duração automática do agendamento; a cobrança do cliente é gerada depois, na tela da OS."
            actions={
              items.length > 0 ? (
                <Button type="button" variant="secondary" size="sm" onClick={addItem}>
                  <Plus className="size-4" />
                  Adicionar serviço
                </Button>
              ) : undefined
            }>
            {items.length === 0 ? (
              <div className="flex flex-col items-start gap-4 rounded-xl bg-surface-2/60 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface text-muted">
                    <Wrench className="size-5" aria-hidden="true" />
                  </span>
                  <div>
                    <p className="text-sm font-medium text-foreground">
                      Nenhum serviço adicionado
                    </p>
                    <p className="mt-0.5 text-[13px] text-muted">
                      Inclua os serviços realizados para calcular o total e a duração automática.
                    </p>
                  </div>
                </div>
                <Button
                  type="button"
                  variant="secondary"
                  className="bg-surface shadow-card"
                  onClick={addItem}>
                  <Plus className="size-4" />
                  Adicionar serviço
                </Button>
              </div>
            ) : (
              <>
                <div className="hidden gap-3 px-1 pb-2 text-xs font-medium text-muted lg:grid lg:grid-cols-[minmax(0,1fr)_6rem_9rem_8rem_2.5rem]">
                  <span>
                    Serviço <span className="text-danger" aria-hidden="true">*</span>
                  </span>
                  <span>Qtd.</span>
                  <span>Valor unit. (R$)</span>
                  <span className="text-right">Total</span>
                  <span />
                </div>

                <div className="space-y-2">
                  {items.map((item) => {
                    const quantityId = `item-${item.key}-quantity`
                    const amountId = `item-${item.key}-amount`
                    const serviceId = `item-${item.key}-service`

                    return (
                      <div
                        key={item.key}
                        className="grid grid-cols-2 gap-3 rounded-xl bg-surface-2/50 p-3 lg:grid-cols-[minmax(0,1fr)_6rem_9rem_8rem_2.5rem] lg:items-start lg:rounded-none lg:bg-transparent lg:p-0 lg:py-1">
                        <SearchSelect
                          id={serviceId}
                          className="col-span-2 min-w-0 lg:col-span-1 lg:space-y-0 lg:[&>label]:sr-only"
                          label="Serviço"
                          required
                          value={item.service_id}
                          disabled={item.missing}
                          placeholder="Buscar serviço..."
                          emptyMessage="Nenhum serviço encontrado"
                          loadOptions={loadServiceOptions}
                          defaultOption={
                            item.service_id ? { value: item.service_id, label: item.name } : null
                          }
                          onChange={(value, option) => {
                            const service = option as ServiceOption | undefined
                            updateItem(item.key, {
                              service_id: value,
                              name: service?.label ?? item.name,
                              amount: service
                                ? centsToReaisInput(service.amount_cents)
                                : item.amount,
                            })
                          }}
                        />
                        <Field
                          label="Qtd."
                          htmlFor={quantityId}
                          className="lg:space-y-0 lg:[&>label]:sr-only">
                          <Input
                            id={quantityId}
                            type="number"
                            min={1}
                            step={1}
                            value={item.quantity}
                            aria-label={`Quantidade de ${item.name || 'serviço'}`}
                            onChange={(event) =>
                              updateItem(item.key, { quantity: event.target.value })
                            }
                          />
                        </Field>
                        <Field
                          label="Valor unit. (R$)"
                          htmlFor={amountId}
                          className="lg:space-y-0 lg:[&>label]:sr-only">
                          <Input
                            id={amountId}
                            type="number"
                            min={0}
                            step="0.01"
                            value={item.amount}
                            aria-label={`Valor unitário de ${item.name || 'serviço'}`}
                            onChange={(event) =>
                              updateItem(item.key, { amount: event.target.value })
                            }
                          />
                        </Field>
                        <div className="flex h-10 items-center justify-between gap-2 lg:justify-end">
                          <span className="text-xs text-muted lg:sr-only">Total</span>
                          <span className="text-sm font-semibold text-foreground">
                            {formatCurrency(itemLineTotalCents(item) / 100)}
                          </span>
                        </div>
                        <div className="flex justify-end">
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => removeItem(item.key)}
                            aria-label={`Remover ${item.name || 'serviço'}`}
                            className="text-danger hover:bg-danger-soft hover:text-danger">
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                        {item.missing && (
                          <p className="col-span-2 text-xs text-warning lg:col-span-5">
                            Serviço removido do catálogo; será excluído ao salvar.
                          </p>
                        )}
                      </div>
                    )
                  })}
                </div>
              </>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-2/60 px-4 py-3">
              <p className="text-sm text-muted">
                {activeItemsCount === 0
                  ? 'Nenhum serviço lançado'
                  : `${activeItemsCount} serviço${activeItemsCount === 1 ? '' : 's'} lançado${
                      activeItemsCount === 1 ? '' : 's'
                    }`}
              </p>
              <p className="text-sm text-muted">
                Total dos serviços:
                <span className="ml-2 text-lg font-semibold text-foreground">
                  {formatCurrency(itemsTotalCents / 100)}
                </span>
              </p>
            </div>

            {itemsError && (
              <p className="text-[13px] text-danger" role="alert">
                {itemsError}
              </p>
            )}
          </FormStep>

          <FormStep
            step={3}
            title="Agendamento e disponibilidade"
            description="Confirme o endereço e a duração, escolha a data e selecione um horário livre. O cálculo considera a jornada, o deslocamento e as OS anteriores e posteriores do técnico.">
            <ServiceOrderSchedulingPanel items={items} serviceOrderId={initial?.id} />
          </FormStep>

          <FormStep
            step={4}
            title="Local de instalação, descrição e observações"
            description="Informe onde o equipamento será instalado, registre o trabalho a executar e as informações de apoio para a equipe.">
            <div className="grid gap-4 lg:grid-cols-2">
              <TextareaField
                name="installation_location"
                label="Local de instalação do equipamento"
                rows={5}
                placeholder="Ex.: atrás do painel, central elétrica, console central, porta-fusíveis..."
                hint="Opcional · descreva o local exato onde o equipamento será instalado."
              />
              <ImageUploader
                label="Foto do local de instalação"
                hint="Opcional · imagem do ponto onde o equipamento será instalado."
                value={installationPhoto}
                onChange={setInstallationPhoto}
              />
              <TextareaField
                name="description"
                label="Descrição do serviço"
                rows={5}
                placeholder="Detalhe o trabalho a realizar"
                hint="Opcional · o que será executado no atendimento."
              />
              <TextareaField
                name="notes"
                label="Observações"
                rows={5}
                placeholder="Opcional"
                hint="Opcional · informações de apoio para a equipe."
              />
            </div>
          </FormStep>

          <FormStep
            step={5}
            title="Revisão e gravação"
            description="Confira o resumo abaixo e conclua o lançamento. Você também pode voltar e ajustar qualquer etapa.">
            {footer}

            <dl className="grid gap-4 rounded-xl bg-surface-2/60 p-4 sm:grid-cols-3">
              <div className="min-w-0">
                <dt className="text-xs text-muted">Serviços</dt>
                <dd className="mt-1 font-semibold text-foreground">
                  {activeItemsCount} serviço{activeItemsCount === 1 ? '' : 's'}
                </dd>
                <dd className="text-xs text-muted">
                  Total {formatCurrency(itemsTotalCents / 100)}
                </dd>
              </div>
              <div className="min-w-0">
                <dt className="text-xs text-muted">Agendamento</dt>
                <dd className="mt-1 text-sm text-foreground">{scheduleSummary}</dd>
                <dd className="text-xs text-muted">
                  {scheduledStartAt ? 'Horário definido em Início/Fim' : 'Opcional'}
                </dd>
              </div>
              <div className="min-w-0">
                <dt className="text-xs text-muted">Prioridade e situação</dt>
                <dd className="mt-1 text-sm text-foreground">
                  {priorityLabel(priorityValue)} · {statusLabel(statusValue)}
                </dd>
              </div>
            </dl>
          </FormStep>

          <div className="sticky bottom-0 z-20 -mx-5 -mb-5 rounded-b-xl bg-surface px-5 py-4 shadow-[0_-12px_24px_-18px_rgb(15_18_25/0.45)]">
            <div className="flex flex-col gap-2">
              {formHasErrors && (
                <p className="text-xs text-danger" role="alert">
                  Revise os campos destacados antes de salvar.
                </p>
              )}
              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p className="min-w-0 text-xs text-muted sm:text-sm">
                  <span>
                    {activeItemsCount} serviço{activeItemsCount === 1 ? '' : 's'} ·{' '}
                    {formatCurrency(itemsTotalCents / 100)}
                  </span>
                  <span className="hidden md:inline"> · {scheduleSummary}</span>
                </p>
                <div className="flex flex-col-reverse gap-2 sm:flex-row">
                  {onCancel ? (
                    <Button
                      type="button"
                      variant="secondary"
                      className="w-full sm:w-auto"
                      onClick={onCancel}>
                      {cancelLabel}
                    </Button>
                  ) : (
                    <ButtonLink
                      to="/service-orders"
                      variant="secondary"
                      className="w-full sm:w-auto">
                      {cancelLabel}
                    </ButtonLink>
                  )}
                  <Button
                    type="submit"
                    loading={submitting}
                    className="w-full sm:w-auto">
                    {submitLabel ?? (mode === 'create' ? 'Criar OS' : 'Salvar alterações')}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
