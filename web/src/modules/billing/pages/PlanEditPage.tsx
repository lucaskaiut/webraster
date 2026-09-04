import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useParams } from 'react-router'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  CheckboxField,
  Form,
  Loading,
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
import { usePlanQuery, useUpdatePlan } from '../hooks/useBilling'
import { planSchema, RECURRENCE_UNITS, type PlanFormValues } from '../schemas/plan.schema'

export default function PlanEditPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const { data: plan, isLoading } = usePlanQuery(id)
  const updatePlan = useUpdatePlan()

  const form = useForm<PlanFormValues>({
    resolver: zodResolver(planSchema),
    defaultValues: {
      name: '',
      description: '',
      price: 0,
      recurrence_value: 30,
      recurrence_unit: 'days',
      free_trial_days: 0,
      active: true,
    },
  })

  useEffect(() => {
    if (!plan) return

    form.reset({
      name: plan.name,
      description: plan.description ?? '',
      price: Number(plan.price),
      recurrence_value: plan.recurrence_value,
      recurrence_unit: plan.recurrence_unit,
      free_trial_days: plan.free_trial_days,
      active: plan.active,
    })
  }, [plan, form])

  const onSubmit = async (values: PlanFormValues) => {
    try {
      await updatePlan.mutateAsync({
        id,
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

  if (isLoading || !plan) {
    return (
      <Page>
        <PageContent>
          <Loading />
        </PageContent>
      </Page>
    )
  }

  return (
    <Page>
      <PageHeader
        title="Editar plano"
        description={plan.name}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Planos', to: '/billing/plans' },
          { label: plan.name },
        ]}
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={onSubmit} className="space-y-8">
              <Section title="Identificação">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="name" label="Nome" />
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
                <Button type="submit" loading={updatePlan.isPending}>
                  Salvar alterações
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
