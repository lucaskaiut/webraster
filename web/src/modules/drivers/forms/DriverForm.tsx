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
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { clientsService } from '@/modules/clients/services/clients.service'
import type { DriverPayload } from '../services/drivers.service'
import { driverSchema, type DriverFormValues } from '../schemas/driver.schema'

interface DriverFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<DriverFormValues>
  submitting: boolean
  onSubmit: (payload: DriverPayload) => Promise<unknown>
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

export function DriverForm({ mode, defaultValues, submitting, onSubmit }: DriverFormProps) {
  const form = useForm<DriverFormValues>({
    resolver: zodResolver(driverSchema),
    defaultValues: {
      client_id: '',
      name: '',
      document: '',
      phone: '',
      email: '',
      cnh_number: '',
      cnh_expires_at: '',
      notes: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: DriverFormValues) => {
    const payload: DriverPayload = {
      client_id: values.client_id,
      name: values.name,
      document: values.document ? onlyDigits(values.document) : null,
      phone: values.phone || null,
      email: values.email || null,
      cnh_number: values.cnh_number || null,
      cnh_expires_at: values.cnh_expires_at || null,
      notes: values.notes || null,
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
          <Section title="Vínculo e identificação">
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
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <TextField name="document" label="CPF" placeholder="Somente números" />
              <TextField name="phone" label="Telefone" placeholder="(41) 99999-9999" />
              <TextField name="email" label="E-mail" type="email" />
              <SwitchField name="is_active" label="Motorista ativo" />
            </div>
          </Section>

          <Section title="CNH e observações">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="cnh_number" label="Número da CNH" />
              <TextField name="cnh_expires_at" label="Validade da CNH" type="date" />
              <TextareaField
                name="notes"
                label="Observações"
                rows={3}
                className="sm:col-span-2"
              />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/drivers" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar motorista' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
