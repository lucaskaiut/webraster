import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  FileField,
  Form,
  SearchSelectField,
  Section,
  SelectField,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { clientsService } from '@/modules/clients/services/clients.service'
import type { VehiclePayload } from '../services/vehicles.service'
import { usePlateLookup } from '../hooks/usePlateLookup'
import { fillVehicleFromLookup } from '../utils/plate-lookup'
import {
  transmissionOptions,
  vehicleSchema,
  type VehicleFormValues,
} from '../schemas/vehicle.schema'
import { DEFAULT_VEHICLE_TYPE, vehicleTypeOptions } from '../lib/vehicle-types'

interface VehicleFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<VehicleFormValues>
  submitting: boolean
  crlvFileUrl?: string | null
  onSubmit: (payload: VehiclePayload) => Promise<unknown>
}

async function loadClientOptions(search: string) {
  const response = await clientsService.list({ search: search || undefined, per_page: 20 })

  return response.data.map((client) => ({
    value: client.id,
    label: client.name,
  }))
}

async function resolveClientLabel(value: string) {
  try {
    const client = await clientsService.get(value)

    return { value: client.id, label: client.name }
  } catch {
    return null
  }
}

export function VehicleForm({
  mode,
  defaultValues,
  submitting,
  crlvFileUrl,
  onSubmit,
}: VehicleFormProps) {
  const form = useForm<VehicleFormValues>({
    resolver: zodResolver(vehicleSchema),
    defaultValues: {
      client_id: '',
      plate: '',
      chassis: '',
      renavam: '',
      brand: '',
      model: '',
      color: '',
      year: '',
      vehicle_type: String(DEFAULT_VEHICLE_TYPE),
      transmission: '',
      odometer: '',
      max_speed_kmh: '',
      speed_hysteresis_percent: '3',
      speed_min_duration_seconds: '30',
      average_consumption: '',
      tank_capacity: '',
      crlv_file: '',
      fipe_code: '',
      fipe_model_year: '',
      fipe_fuel: '',
      fipe_reference_month: '',
      fipe_value: '',
      fipe_model: '',
      fipe_brand: '',
      fipe_score: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const {
    hint: plateHint,
    loading: plateLoading,
    lookup: lookupPlate,
  } = usePlateLookup((data) => fillVehicleFromLookup(form, data))

  const handleSubmit = async (values: VehicleFormValues) => {
    const payload: VehiclePayload = {
      client_id: values.client_id,
      plate: values.plate,
      chassis: values.chassis || null,
      renavam: values.renavam || null,
      brand: values.brand || null,
      model: values.model || null,
      color: values.color || null,
      year: values.year ? Number(values.year) : null,
      vehicle_type: values.vehicle_type ? Number(values.vehicle_type) : null,
      transmission: values.transmission || null,
      odometer: values.odometer ? Number(values.odometer) : null,
      max_speed_kmh: values.max_speed_kmh ? Number(values.max_speed_kmh) : null,
      speed_hysteresis_percent: values.speed_hysteresis_percent
        ? Number(values.speed_hysteresis_percent)
        : null,
      speed_min_duration_seconds: values.speed_min_duration_seconds
        ? Number(values.speed_min_duration_seconds)
        : null,
      average_consumption: values.average_consumption
        ? Number(values.average_consumption)
        : null,
      tank_capacity: values.tank_capacity ? Number(values.tank_capacity) : null,
      crlv_file: values.crlv_file || null,
      fipe_code: values.fipe_code || null,
      fipe_model_year: values.fipe_model_year || null,
      fipe_fuel: values.fipe_fuel || null,
      fipe_reference_month: values.fipe_reference_month || null,
      fipe_value: values.fipe_value || null,
      fipe_model: values.fipe_model || null,
      fipe_brand: values.fipe_brand || null,
      fipe_score: values.fipe_score ? Number(values.fipe_score) : null,
      is_active: values.is_active,
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
          <Section title="Cliente e identificação">
            <div className="grid gap-4 sm:grid-cols-2">
              <SearchSelectField
                name="client_id"
                label="Cliente"
                required
                className="sm:col-span-2"
                placeholder="Buscar cliente..."
                emptyMessage="Nenhum cliente encontrado"
                loadOptions={loadClientOptions}
                resolveLabel={resolveClientLabel}
              />
              <TextField
                name="plate"
                label="Placa"
                required
                placeholder="ABC1D23"
                hint={plateHint}
                loading={plateLoading}
                onBlur={() => lookupPlate(form.getValues('plate'))}
              />
              <TextField name="renavam" label="RENAVAM" />
              <TextField name="chassis" label="Chassi" className="sm:col-span-2" loading={plateLoading} />
              <SwitchField name="is_active" label="Veículo ativo" />
            </div>
          </Section>

          <Section title="Características">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="brand" label="Marca" loading={plateLoading} />
              <TextField name="model" label="Modelo" loading={plateLoading} />
              <TextField name="color" label="Cor" loading={plateLoading} />
              <TextField name="year" label="Ano" type="number" placeholder="2024" loading={plateLoading} />
              <SelectField
                name="vehicle_type"
                label="Tipo de veículo"
                required
                placeholder="Selecione"
                options={vehicleTypeOptions}
                loading={plateLoading}
              />
              <SelectField
                name="transmission"
                label="Transmissão"
                placeholder="Selecione"
                options={[...transmissionOptions]}
                loading={plateLoading}
              />
              <TextField
                name="odometer"
                label="Odômetro (km)"
                type="number"
                placeholder="0"
                min={0}
              />
              <TextField
                name="max_speed_kmh"
                label="Velocidade máxima permitida (km/h)"
                type="number"
                placeholder="80"
                min={1}
                max={300}
                hint="Limite usado para detectar excessos de velocidade."
              />
              <TextField
                name="speed_hysteresis_percent"
                label="Margem de histerese (%)"
                type="number"
                placeholder="3"
                min={1}
                max={20}
                hint="Evita abrir/fechar o evento por oscilações do GPS."
              />
              <TextField
                name="speed_min_duration_seconds"
                label="Duração mínima (segundos)"
                type="number"
                placeholder="30"
                min={1}
                max={3600}
                hint="Excessos mais curtos são descartados."
              />
            </div>
          </Section>

          <Section title="Consumo e tanque">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="average_consumption"
                label="Consumo médio (km/L)"
                type="number"
                step="0.1"
                placeholder="0.0"
                min={0}
              />
              <TextField
                name="tank_capacity"
                label="Capacidade do tanque (L)"
                type="number"
                step="0.1"
                placeholder="0.0"
                min={0}
              />
            </div>
          </Section>

          <Section title="Tabela FIPE" description="Preenchida automaticamente pela consulta de placa.">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="fipe_code" label="Código FIPE" loading={plateLoading} />
              <TextField name="fipe_value" label="Valor" loading={plateLoading} />
              <TextField name="fipe_brand" label="Marca" loading={plateLoading} />
              <TextField name="fipe_model" label="Modelo" loading={plateLoading} />
              <TextField name="fipe_model_year" label="Ano modelo" loading={plateLoading} />
              <TextField name="fipe_fuel" label="Combustível" loading={plateLoading} />
              <TextField
                name="fipe_reference_month"
                label="Mês de referência"
                loading={plateLoading}
              />
              <TextField
                name="fipe_score"
                label="Score"
                type="number"
                min={0}
                loading={plateLoading}
              />
            </div>
          </Section>

          <Section title="Documentos">
            <FileField
              name="crlv_file"
              label="CRLV-e"
              hint="Envie o CRLV-e digitalizado (PDF ou imagem)."
              currentUrl={crlvFileUrl}
              className="sm:col-span-2"
            />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/vehicles" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar veículo' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
