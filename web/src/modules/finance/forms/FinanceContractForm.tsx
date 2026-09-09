import { useForm } from 'react-hook-form'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  Section,
  SelectField,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { toLocalIsoDate } from '@/shared/utils/format'
import { clientsService } from '@/modules/clients/services/clients.service'
import type { FinanceContract } from '@/shared/types/models'
import type { FinanceContractPayload } from '../services/finance.service'
import { financeService } from '../services/finance.service'
import { financeContractSchema, type FinanceContractFormValues } from '../schemas/contract.schema'
import { centsToReais, PERIODICITY_OPTIONS, reaisToCents } from '../lib/labels'

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

async function loadPlanOptions(search: string) {
  const response = await financeService.listPlans({
    search: search || undefined,
    per_page: 20,
    is_active: true,
  })
  return response.data.map((plan) => ({
    value: plan.id,
    label: `${plan.name} · R$ ${plan.amount}`,
  }))
}

async function resolvePlanLabel(value: string) {
  try {
    const plan = await financeService.getPlan(value)
    return { value: plan.id, label: plan.name }
  } catch {
    return null
  }
}

interface FinanceContractFormProps {
  mode: 'create' | 'edit'
  initial?: FinanceContract | null
  submitting: boolean
  onSubmit: (payload: FinanceContractPayload) => Promise<unknown>
}

export function FinanceContractForm({
  mode,
  initial,
  submitting,
  onSubmit,
}: FinanceContractFormProps) {
  const form = useForm<FinanceContractFormValues>({
    resolver: formResolver<FinanceContractFormValues>(financeContractSchema),
    defaultValues: {
      client_id: initial?.client_id ?? '',
      plan_id: initial?.plan_id ?? '',
      starts_at: initial?.starts_at ?? toLocalIsoDate(),
      ends_at: initial?.ends_at ?? '',
      periodicity: initial?.periodicity ?? 'monthly',
      due_day: initial?.due_day ?? 10,
      amount: initial ? centsToReais(initial.amount_cents) : 0,
      discount: initial ? centsToReais(initial.discount_cents) : 0,
      fine_percent: initial?.fine_percent ?? 0,
      interest_percent: initial?.interest_percent ?? 0,
      device_quantity: initial?.device_quantity ?? 0,
      auto_renew: initial?.auto_renew ?? true,
      block_on_overdue: initial?.block_on_overdue ?? true,
      block_after_days: initial?.block_after_days ?? 5,
      notes: initial?.notes ?? '',
    },
  })

  const handleSubmit = async (values: FinanceContractFormValues) => {
    const payload: FinanceContractPayload = {
      client_id: values.client_id,
      plan_id: values.plan_id || null,
      starts_at: values.starts_at,
      ends_at: values.ends_at || null,
      periodicity: values.periodicity,
      due_day: values.due_day,
      amount_cents: reaisToCents(values.amount),
      discount_cents: reaisToCents(values.discount),
      fine_percent: values.fine_percent,
      interest_percent: values.interest_percent,
      device_quantity: values.device_quantity,
      auto_renew: values.auto_renew,
      block_on_overdue: values.block_on_overdue,
      block_after_days: values.block_after_days,
      notes: values.notes || null,
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
          <Section title="Cliente e plano">
            <div className="grid gap-4 sm:grid-cols-2">
              <SearchSelectField
                name="client_id"
                label="Cliente"
                required
                loadOptions={loadClientOptions}
                resolveLabel={resolveClientLabel}
                placeholder="Buscar cliente..."
              />
              <SearchSelectField
                name="plan_id"
                label="Plano"
                loadOptions={loadPlanOptions}
                resolveLabel={resolvePlanLabel}
                placeholder="Opcional — buscar plano..."
              />
            </div>
          </Section>

          <Section title="Vigência e valores">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="starts_at" label="Início" type="date" required />
              <TextField name="ends_at" label="Término" type="date" />
              <SelectField
                name="periodicity"
                label="Periodicidade"
                options={PERIODICITY_OPTIONS}
                required
              />
              <TextField name="due_day" label="Dia de vencimento" type="number" min={1} max={28} />
              <TextField name="amount" label="Valor (R$)" type="number" step="0.01" required />
              <TextField name="discount" label="Desconto (R$)" type="number" step="0.01" />
              <TextField name="fine_percent" label="Multa (%)" type="number" step="0.01" />
              <TextField name="interest_percent" label="Juros (%)" type="number" step="0.01" />
              <TextField name="device_quantity" label="Qtd. dispositivos" type="number" />
            </div>
          </Section>

          <Section title="Bloqueio e renovação">
            <div className="grid gap-4 sm:grid-cols-2">
              <SwitchField name="auto_renew" label="Renovação automática" />
              <SwitchField name="block_on_overdue" label="Bloquear em atraso" />
              <TextField
                name="block_after_days"
                label="Dias após vencimento para bloquear"
                type="number"
              />
            </div>
            <TextareaField name="notes" label="Observações" rows={3} />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink
              to={mode === 'edit' && initial ? `/finance/contracts/${initial.id}` : '/finance/contracts'}
              variant="secondary"
            >
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar contrato' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
