import { useForm, useWatch } from 'react-hook-form'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Checkbox,
  Form,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { formatCurrency } from '@/shared/utils/format'
import { reaisToCents } from '@/modules/finance/lib/labels'
import type { ServicePayload } from '../services/services.service'
import { serviceSchema, type ServiceFormValues } from '../schemas/service.schema'
import { useServicesQuery } from '../hooks/useServices'

interface ServiceFormProps {
  mode: 'create' | 'edit'
  /** Serviço em edição, removido da lista de vínculos. */
  serviceId?: string
  defaultValues?: Partial<ServiceFormValues>
  submitting: boolean
  onSubmit: (payload: ServicePayload) => Promise<unknown>
}

export function ServiceForm({ mode, serviceId, defaultValues, submitting, onSubmit }: ServiceFormProps) {
  const form = useForm<ServiceFormValues>({
    resolver: formResolver<ServiceFormValues>(serviceSchema),
    defaultValues: {
      name: '',
      amount: 0,
      linked_service_ids: [],
      ...defaultValues,
    },
  })

  const servicesQuery = useServicesQuery({ per_page: 100 })
  const linkableServices = (servicesQuery.data?.data ?? []).filter((service) => service.id !== serviceId)
  const linkedIds = useWatch({ control: form.control, name: 'linked_service_ids' }) ?? []

  const toggleLinked = (id: string, checked: boolean) => {
    const current = form.getValues('linked_service_ids') ?? []
    form.setValue(
      'linked_service_ids',
      checked ? [...current, id] : current.filter((value) => value !== id),
      { shouldDirty: true },
    )
  }

  const handleSubmit = async (values: ServiceFormValues) => {
    const payload: ServicePayload = {
      name: values.name,
      amount_cents: reaisToCents(values.amount),
      linked_service_ids: values.linked_service_ids,
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

          <Section
            title="Serviços vinculados"
            description="Ao contratar este serviço no pedido do cliente, os serviços vinculados são sugeridos na ordem de serviço do cadastro (ex.: instalação)."
          >
            {servicesQuery.isPending ? (
              <p className="text-sm text-muted">Carregando serviços...</p>
            ) : linkableServices.length === 0 ? (
              <p className="text-sm text-muted">Nenhum outro serviço cadastrado para vincular.</p>
            ) : (
              <div className="grid gap-2 sm:grid-cols-2">
                {linkableServices.map((service) => (
                  <Checkbox
                    key={service.id}
                    label={`${service.name} · ${formatCurrency(service.amount_cents / 100)}`}
                    checked={linkedIds.includes(service.id)}
                    onChange={(event) => toggleLinked(service.id, event.target.checked)}
                  />
                ))}
              </div>
            )}
            {form.formState.errors.linked_service_ids && (
              <p className="text-[13px] text-danger" role="alert">
                Verifique os serviços vinculados selecionados.
              </p>
            )}
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
