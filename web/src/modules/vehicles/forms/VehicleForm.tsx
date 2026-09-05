import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  Section,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { clientsService } from '@/modules/clients/services/clients.service'
import type { VehiclePayload } from '../services/vehicles.service'
import { vehicleSchema, type VehicleFormValues } from '../schemas/vehicle.schema'

interface VehicleFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<VehicleFormValues>
  submitting: boolean
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

export function VehicleForm({ mode, defaultValues, submitting, onSubmit }: VehicleFormProps) {
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
      is_active: true,
      ...defaultValues,
    },
  })

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
              <TextField name="plate" label="Placa" required placeholder="ABC1D23" />
              <TextField name="renavam" label="RENAVAM" />
              <TextField name="chassis" label="Chassi" className="sm:col-span-2" />
              <SwitchField name="is_active" label="Veículo ativo" />
            </div>
          </Section>

          <Section title="Características">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="brand" label="Marca" />
              <TextField name="model" label="Modelo" />
              <TextField name="color" label="Cor" />
              <TextField name="year" label="Ano" type="number" placeholder="2024" />
            </div>
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
