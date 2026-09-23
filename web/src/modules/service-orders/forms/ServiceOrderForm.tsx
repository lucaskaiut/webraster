import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  CheckboxField,
  Form,
  SearchSelectField,
  Section,
  SelectField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { clientsService } from '@/modules/clients/services/clients.service'
import { vehiclesService } from '@/modules/vehicles/services/vehicles.service'
import { equipmentsService } from '@/modules/equipments/services/equipments.service'
import { usersService } from '@/modules/users/services/users.service'
import type { ServiceOrder } from '@/shared/types/models'
import type { ServiceOrderPayload } from '../services/service-orders.service'
import { serviceOrderSchema, type ServiceOrderFormValues } from '../schemas/service-order.schema'
import {
  SERVICE_ORDER_PRIORITY_OPTIONS,
  SERVICE_ORDER_TYPE_OPTIONS,
} from '../lib/labels'

import { parseLocalDateTime, toLocalDateTimeValue } from '@/shared/utils/date'

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

async function loadEquipmentOptions(search: string) {
  const response = await equipmentsService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((item) => ({
    value: item.id,
    label: [item.imei, item.model].filter(Boolean).join(' · ') || 'Equipamento',
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
  const response = await usersService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((user) => ({ value: user.id, label: user.name }))
}

async function resolveTechnicianLabel(value: string) {
  try {
    const user = await usersService.get(value)
    return { value: user.id, label: user.name }
  } catch {
    return null
  }
}

interface ServiceOrderFormProps {
  mode: 'create' | 'edit'
  initial?: ServiceOrder | null
  submitting: boolean
  onSubmit: (payload: ServiceOrderPayload) => Promise<unknown>
}

export function ServiceOrderForm({ mode, initial, submitting, onSubmit }: ServiceOrderFormProps) {
  const form = useForm<ServiceOrderFormValues>({
    resolver: zodResolver(serviceOrderSchema),
    defaultValues: {
      type: initial?.type ?? 'installation',
      priority: initial?.priority ?? 'normal',
      client_id: initial?.client_id ?? '',
      vehicle_id: initial?.vehicle_id ?? '',
      equipment_id: initial?.equipment_id ?? '',
      technician_id: initial?.technician_id ?? '',
      scheduled_start_at: toFormDateTime(initial?.scheduled_start_at),
      scheduled_end_at: toFormDateTime(initial?.scheduled_end_at),
      description: initial?.description ?? '',
      notes: initial?.notes ?? '',
      ignore_schedule_conflict: false,
    },
  })

  const clientId = useWatch({ control: form.control, name: 'client_id' })

  const handleSubmit = async (values: ServiceOrderFormValues) => {
    const payload: ServiceOrderPayload = {
      type: values.type,
      priority: values.priority,
      client_id: values.client_id,
      vehicle_id: values.vehicle_id || null,
      equipment_id: values.equipment_id || null,
      technician_id: values.technician_id || null,
      scheduled_start_at: fromFormDateTime(values.scheduled_start_at ?? ''),
      scheduled_end_at: fromFormDateTime(values.scheduled_end_at ?? ''),
      description: values.description || null,
      notes: values.notes || null,
      ignore_schedule_conflict: values.ignore_schedule_conflict ?? false,
    }

    try {
      await onSubmit(payload)
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
          <Section title="Dados da OS">
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectField
                name="type"
                label="Tipo"
                required
                options={SERVICE_ORDER_TYPE_OPTIONS}
              />
              <SelectField
                name="priority"
                label="Prioridade"
                required
                options={SERVICE_ORDER_PRIORITY_OPTIONS}
              />
              <SearchSelectField
                name="client_id"
                label="Cliente"
                required
                className="sm:col-span-2"
                placeholder="Buscar cliente..."
                emptyMessage="Nenhum cliente encontrado"
                loadOptions={loadClientOptions}
                resolveLabel={resolveClientLabel}
                onSelectOption={() => {
                  form.setValue('vehicle_id', '')
                }}
              />
              <SearchSelectField
                name="vehicle_id"
                label="Veículo"
                hint="Opcional"
                placeholder="Buscar veículo..."
                emptyMessage="Nenhum veículo encontrado"
                loadOptions={(search) => loadVehicleOptions(search, clientId)}
                resolveLabel={resolveVehicleLabel}
              />
              <SearchSelectField
                name="equipment_id"
                label="Dispositivo"
                hint="Opcional"
                placeholder="Buscar equipamento..."
                emptyMessage="Nenhum equipamento encontrado"
                loadOptions={loadEquipmentOptions}
                resolveLabel={resolveEquipmentLabel}
              />
              <SearchSelectField
                name="technician_id"
                label="Técnico responsável"
                className="sm:col-span-2"
                placeholder="Buscar usuário..."
                emptyMessage="Nenhum técnico encontrado"
                loadOptions={loadTechnicianOptions}
                resolveLabel={resolveTechnicianLabel}
              />
            </div>
          </Section>

          <Section title="Agendamento">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="scheduled_start_at" label="Início" type="datetime-local" />
              <TextField name="scheduled_end_at" label="Fim" type="datetime-local" />
              <CheckboxField
                name="ignore_schedule_conflict"
                label="Ignorar conflito de agenda (se houver)"
                className="sm:col-span-2"
              />
            </div>
          </Section>

          <Section title="Descrição">
            <div className="grid gap-4">
              <TextareaField name="description" label="Descrição do serviço" placeholder="Detalhe o trabalho a realizar" />
              <TextareaField name="notes" label="Observações" placeholder="Opcional" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/service-orders" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar OS' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
