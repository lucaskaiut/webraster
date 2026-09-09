import { useForm } from 'react-hook-form'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SelectField,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import type { FinancePlan } from '@/shared/types/models'
import type { FinancePlanPayload } from '../services/finance.service'
import { financePlanSchema, type FinancePlanFormValues } from '../schemas/plan.schema'
import { centsToReais, PERIODICITY_OPTIONS, reaisToCents } from '../lib/labels'

interface FinancePlanFormProps {
  mode: 'create' | 'edit'
  initial?: FinancePlan | null
  submitting: boolean
  onSubmit: (payload: FinancePlanPayload) => Promise<unknown>
}

export function FinancePlanForm({ mode, initial, submitting, onSubmit }: FinancePlanFormProps) {
  const form = useForm<FinancePlanFormValues>({
    resolver: formResolver<FinancePlanFormValues>(financePlanSchema),
    defaultValues: {
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      amount: initial ? centsToReais(initial.amount_cents) : 0,
      periodicity: initial?.periodicity ?? 'monthly',
      device_limit: initial?.device_limit != null ? String(initial.device_limit) : '',
      is_active: initial?.is_active ?? true,
    },
  })

  const handleSubmit = async (values: FinancePlanFormValues) => {
    const payload: FinancePlanPayload = {
      name: values.name,
      description: values.description || null,
      amount_cents: reaisToCents(values.amount),
      periodicity: values.periodicity,
      device_limit: values.device_limit.trim() ? Number(values.device_limit) : null,
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
          <Section title="Identificação">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <TextField
                name="amount"
                label="Valor (R$)"
                type="number"
                step="0.01"
                required
              />
              <SelectField
                name="periodicity"
                label="Periodicidade"
                options={PERIODICITY_OPTIONS}
                required
              />
              <TextField
                name="device_limit"
                label="Limite de dispositivos"
                type="number"
                placeholder="Opcional"
              />
              <SwitchField name="is_active" label="Plano ativo" />
            </div>
            <TextareaField name="description" label="Descrição" rows={3} />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/finance/plans" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar plano' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
