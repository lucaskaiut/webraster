import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  CheckboxField,
  Form,
  Page,
  PageContent,
  PageHeader,
  Section,
  SelectField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { useCreatePlan } from '../hooks/useBilling'
import { planSchema, RECURRENCE_UNITS, type PlanFormValues } from '../schemas/plan.schema'

export default function PlanCreatePage() {
  const navigate = useNavigate()
  const createPlan = useCreatePlan()

  const form = useForm<PlanFormValues>({
    resolver: zodResolver(planSchema),
    defaultValues: {
      name: '',
      description: '',
      price: 49.9,
      recurrence_value: 30,
      recurrence_unit: 'days',
      free_trial_days: 7,
      active: true,
    },
  })

  const onSubmit = async (values: PlanFormValues) => {
    try {
      await createPlan.mutateAsync({
        ...values,
        description: values.description || null,
      })
      navigate('/billing/plans')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Page>
      <PageHeader
        title="Novo plano"
        description="Defina preço, recorrência e período de testes."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Planos', to: '/billing/plans' },
          { label: 'Novo plano' },
        ]}
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={onSubmit} className="space-y-8">
              <Section title="Identificação">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="name" label="Nome" placeholder="Plano Básico" />
                  <TextField name="price" label="Preço (R$)" type="number" step="0.01" />
                </div>
                <TextareaField name="description" label="Descrição" rows={3} />
              </Section>

              <Section title="Recorrência e trial">
                <div className="grid gap-4 sm:grid-cols-3">
                  <TextField name="recurrence_value" label="Intervalo" type="number" />
                  <SelectField
                    name="recurrence_unit"
                    label="Unidade"
                    options={RECURRENCE_UNITS.map((unit) => ({
                      value: unit.value,
                      label: unit.label,
                    }))}
                  />
                  <TextField name="free_trial_days" label="Dias de trial" type="number" />
                </div>
                <CheckboxField name="active" label="Plano ativo" />
              </Section>

              <div className="flex justify-end gap-3">
                <ButtonLink to="/billing/plans" variant="secondary">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={createPlan.isPending}>
                  Criar plano
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
