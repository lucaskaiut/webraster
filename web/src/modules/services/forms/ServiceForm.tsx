import { useForm } from 'react-hook-form'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { reaisToCents } from '@/modules/finance/lib/labels'
import type { ServicePayload } from '../services/services.service'
import { serviceSchema, type ServiceFormValues } from '../schemas/service.schema'

interface ServiceFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<ServiceFormValues>
  submitting: boolean
  onSubmit: (payload: ServicePayload) => Promise<unknown>
}

export function ServiceForm({ mode, defaultValues, submitting, onSubmit }: ServiceFormProps) {
  const form = useForm<ServiceFormValues>({
    resolver: formResolver<ServiceFormValues>(serviceSchema),
    defaultValues: {
      name: '',
      amount: 0,
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: ServiceFormValues) => {
    const payload: ServicePayload = {
      name: values.name,
      amount_cents: reaisToCents(values.amount),
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
          <Section title="Dados do serviço">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <TextField name="amount" label="Valor (R$)" type="number" step="0.01" required />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/services" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar serviço' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
