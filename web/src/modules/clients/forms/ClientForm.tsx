import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import type { ClientPayload } from '../services/clients.service'
import { clientSchema, type ClientFormValues } from '../schemas/client.schema'

interface ClientFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<ClientFormValues>
  submitting: boolean
  onSubmit: (payload: ClientPayload) => Promise<unknown>
}

export function ClientForm({ mode, defaultValues, submitting, onSubmit }: ClientFormProps) {
  const form = useForm<ClientFormValues>({
    resolver: zodResolver(clientSchema),
    defaultValues: {
      name: '',
      document: '',
      email: '',
      phone: '',
      street: '',
      number: '',
      complement: '',
      neighborhood: '',
      city: '',
      state: '',
      zip: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: ClientFormValues) => {
    const payload: ClientPayload = {
      name: values.name,
      document: onlyDigits(values.document),
      email: values.email || null,
      phone: values.phone || null,
      street: values.street || null,
      number: values.number || null,
      complement: values.complement || null,
      neighborhood: values.neighborhood || null,
      city: values.city || null,
      state: values.state ? values.state.toUpperCase() : null,
      zip: values.zip ? onlyDigits(values.zip) : null,
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
          <Section title="Informações básicas">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <TextField name="document" label="CPF/CNPJ" required placeholder="Somente números" />
              <TextField name="email" label="E-mail" type="email" />
              <TextField name="phone" label="Telefone" placeholder="(41) 99999-9999" />
              <SwitchField name="is_active" label="Cliente ativo" />
            </div>
          </Section>

          <Section title="Endereço" description="Informações opcionais de localização.">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="street" label="Logradouro" className="sm:col-span-2" />
              <TextField name="number" label="Número" />
              <TextField name="complement" label="Complemento" />
              <TextField name="neighborhood" label="Bairro" />
              <TextField name="city" label="Cidade" />
              <TextField name="state" label="UF" placeholder="PR" maxLength={2} />
              <TextField name="zip" label="CEP" placeholder="Somente números" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/clients" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar cliente' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
