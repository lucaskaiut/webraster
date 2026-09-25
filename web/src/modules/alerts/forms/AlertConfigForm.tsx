import { useForm, useWatch } from 'react-hook-form'
import {
  Button,
  Card,
  CardContent,
  CardHeader,
  CheckboxField,
  Form,
  SearchSelectField,
  Section,
  SelectField,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { clientsService } from '@/modules/clients/services/clients.service'
import { vehiclesService } from '@/modules/vehicles/services/vehicles.service'
import type { AlertConfig, AlertType } from '@/shared/types/models'
import type { AlertConfigPayload } from '../services/alerts.service'
import {
  ALERT_CONFIG_TYPES,
  alertConfigSchema,
  type AlertConfigFormValues,
  type AlertConfigType,
} from '../schemas/alert-config.schema'

const TYPE_OPTIONS = [
  { value: 'speed', label: 'Excesso de velocidade' },
  { value: 'ignition_on', label: 'Ignição ligada' },
  { value: 'ignition_off', label: 'Ignição desligada' },
  { value: 'sos', label: 'SOS' },
  { value: 'offline', label: 'Dispositivo offline' },
  { value: 'battery', label: 'Bateria baixa' },
  { value: 'jamming', label: 'Jamming' },
  { value: 'device_alarm', label: 'Alarmes do dispositivo (Traccar)' },
]

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

async function loadVehicleOptions(search: string) {
  const response = await vehiclesService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((vehicle) => ({
    value: vehicle.id,
    label: `${vehicle.plate}${vehicle.client?.name ? ` · ${vehicle.client.name}` : ''}`,
  }))
}

async function resolveVehicleLabel(value: string) {
  try {
    const vehicle = await vehiclesService.get(value)
    return {
      value: vehicle.id,
      label: `${vehicle.plate}${vehicle.client?.name ? ` · ${vehicle.client.name}` : ''}`,
    }
  } catch {
    return null
  }
}

function toAlertConfigType(type: AlertType | undefined): AlertConfigType {
  if (type && (ALERT_CONFIG_TYPES as readonly string[]).includes(type)) {
    return type as AlertConfigType
  }
  return 'speed'
}

interface AlertConfigFormProps {
  mode: 'create' | 'edit'
  initial?: AlertConfig | null
  submitting: boolean
  onSubmit: (payload: AlertConfigPayload) => Promise<unknown>
  onCancel?: () => void
}

export function AlertConfigForm({ mode, initial, submitting, onSubmit, onCancel }: AlertConfigFormProps) {
  const form = useForm<AlertConfigFormValues>({
    resolver: formResolver<AlertConfigFormValues>(alertConfigSchema),
    defaultValues: {
      name: initial?.name ?? '',
      type: toAlertConfigType(initial?.type),
      client_id: initial?.client_id ?? '',
      vehicle_id: initial?.vehicle_id ?? '',
      is_enabled: initial?.is_enabled ?? true,
      notify_in_app: initial?.notify_in_app ?? true,
      notify_email: initial?.notify_email ?? false,
      notify_push: initial?.notify_push ?? true,
      speed_limit_kmh: initial?.settings.speed_limit_kmh ?? 80,
      min_duration_seconds: initial?.settings.min_duration_seconds ?? 60,
      offline_minutes: initial?.settings.offline_minutes ?? 15,
      battery_threshold: initial?.settings.battery_threshold ?? 20,
    },
  })

  const type = useWatch({ control: form.control, name: 'type' })

  const handleSubmit = async (values: AlertConfigFormValues) => {
    const settings: AlertConfigPayload['settings'] = {}

    if (values.type === 'speed') {
      settings.speed_limit_kmh = values.speed_limit_kmh
      settings.min_duration_seconds = values.min_duration_seconds
    }
    if (values.type === 'offline') {
      settings.offline_minutes = values.offline_minutes
    }
    if (values.type === 'battery') {
      settings.battery_threshold = values.battery_threshold
    }

    const payload: AlertConfigPayload = {
      name: values.name || null,
      type: values.type,
      client_id: values.client_id || null,
      vehicle_id: values.vehicle_id || null,
      is_enabled: values.is_enabled,
      notify_in_app: values.notify_in_app,
      notify_email: values.notify_email,
      notify_push: values.notify_push,
      settings,
    }

    try {
      await onSubmit(payload)
      if (mode === 'create') {
        form.reset({
          name: '',
          type: 'speed',
          client_id: '',
          vehicle_id: '',
          is_enabled: true,
          notify_in_app: true,
          notify_email: false,
          notify_push: true,
          speed_limit_kmh: 80,
          min_duration_seconds: 60,
          offline_minutes: 15,
          battery_threshold: 20,
        })
      }
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardHeader
        title={mode === 'create' ? 'Novo alerta' : 'Editar alerta'}
        description="Defina o evento, o escopo (veículo, cliente ou todos) e os valores do disparo."
      />
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section title="Escopo">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="name"
                label="Nome"
                placeholder="Opcional — ex.: Limite rodovia"
                className="sm:col-span-2"
              />
              <SearchSelectField
                name="client_id"
                label="Cliente"
                hint="Deixe em branco para todos os clientes."
                placeholder="Buscar cliente..."
                emptyMessage="Nenhum cliente encontrado"
                loadOptions={loadClientOptions}
                resolveLabel={resolveClientLabel}
                onSelectOption={() => form.setValue('vehicle_id', '')}
              />
              <SearchSelectField
                name="vehicle_id"
                label="Veículo"
                hint="Deixe em branco para todos os veículos (ou use só o cliente)."
                placeholder="Buscar veículo..."
                emptyMessage="Nenhum veículo encontrado"
                loadOptions={loadVehicleOptions}
                resolveLabel={resolveVehicleLabel}
                onSelectOption={() => form.setValue('client_id', '')}
              />
            </div>
          </Section>

          <Section title="Evento">
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectField
                name="type"
                label="Tipo de evento"
                required
                className="sm:col-span-2"
                options={TYPE_OPTIONS}
              />

              {type === 'speed' && (
                <>
                  <TextField name="speed_limit_kmh" label="Limite (km/h)" type="number" required />
                  <TextField
                    name="min_duration_seconds"
                    label="Tempo mínimo (s)"
                    type="number"
                    required
                  />
                </>
              )}
              {type === 'offline' && (
                <TextField
                  name="offline_minutes"
                  label="Minutos sem comunicação"
                  type="number"
                  required
                />
              )}
              {type === 'battery' && (
                <TextField
                  name="battery_threshold"
                  label="Percentual mínimo"
                  type="number"
                  required
                />
              )}
            </div>
          </Section>

          <Section title="Notificações">
            <div className="grid gap-4 sm:grid-cols-2">
              <SwitchField name="is_enabled" label="Regra ativa" className="sm:col-span-2" />
              <CheckboxField name="notify_in_app" label="Notificar no sistema" />
              <CheckboxField name="notify_email" label="Enviar e-mail" />
              <CheckboxField name="notify_push" label="Enviar push" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            {onCancel && (
              <Button type="button" variant="secondary" onClick={onCancel}>
                Cancelar
              </Button>
            )}
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Salvar alerta' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
