import { useEffect, useState, type ReactNode } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  TextareaField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { formatCurrency } from '@/shared/utils/format'
import { parseLocalDateTime } from '@/shared/utils/date'
import { servicesService } from '@/modules/services/services/services.service'
import { AddressAutocompleteField } from './AddressAutocompleteField'
import { AttachmentDropzone } from './AttachmentDropzone'
import { ServiceOrderSchedulingPanel } from './ServiceOrderSchedulingPanel'
import { formatMinutes } from '../lib/duration'
import { useDefaultTechnician } from '../hooks/useDefaultTechnician'
import type { ServiceOrderPayload } from '../services/service-orders.service'
import { serviceOrderSchema, type ServiceOrderFormValues } from '../schemas/service-order.schema'

function fromFormDateTime(value: string | undefined): string | null {
  if (!value) return null
  const date = parseLocalDateTime(value)
  if (!date) return null
  return date.toISOString()
}

interface ServiceOption extends SearchSelectOption {
  amount_cents: number
}

async function loadServiceOptions(search: string): Promise<ServiceOption[]> {
  const response = await servicesService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((service) => ({
    value: service.id,
    label: service.name,
    description: [
      formatCurrency(service.amount_cents / 100),
      service.estimated_minutes ? formatMinutes(service.estimated_minutes) : null,
    ]
      .filter(Boolean)
      .join(' · '),
    amount_cents: service.amount_cents,
  }))
}

function FormStep({
  step,
  title,
  description,
  children,
}: {
  step: number
  title: string
  description?: string
  children: ReactNode
}) {
  const titleId = `service-order-step-${step}`

  return (
    <section aria-labelledby={titleId} className="space-y-5">
      <div className="flex min-w-0 items-start gap-3">
        <span
          aria-hidden="true"
          className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-semibold text-primary">
          {step}
        </span>
        <div className="min-w-0">
          <h2 id={titleId} className="text-base font-semibold text-foreground">
            {title}
          </h2>
          {description && <p className="mt-0.5 max-w-3xl text-[13px] text-muted">{description}</p>}
        </div>
      </div>
      {children}
    </section>
  )
}

interface ServiceOrderFormProps {
  submitting: boolean
  onSubmit: (payload: ServiceOrderPayload, attachments: File[]) => Promise<unknown>
}

/**
 * Lançamento de OS do agendamento: endereço, serviço, horário e documento.
 * O técnico é fixo (localizado pelo e-mail configurado) e não aparece na tela.
 */
export function ServiceOrderForm({ submitting, onSubmit }: ServiceOrderFormProps) {
  const technician = useDefaultTechnician()
  const form = useForm<ServiceOrderFormValues>({
    resolver: zodResolver(serviceOrderSchema),
    defaultValues: {
      address: '',
      service_id: '',
      technician_id: '',
      scheduled_start_at: '',
      scheduled_end_at: '',
      description: '',
    },
  })

  const serviceId = useWatch({ control: form.control, name: 'service_id' })
  const [service, setService] = useState<ServiceOption | null>(null)
  const [attachments, setAttachments] = useState<File[]>([])
  const [attachmentsError, setAttachmentsError] = useState<string | null>(null)

  const technicianId = technician.data?.id ?? ''

  useEffect(() => {
    form.setValue('technician_id', technicianId)
  }, [form, technicianId])

  const clearSchedule = () => {
    form.setValue('scheduled_start_at', '')
    form.setValue('scheduled_end_at', '')
  }

  const formHasErrors = Object.keys(form.formState.errors).length > 0 || Boolean(attachmentsError)

  const handleSubmit = async (values: ServiceOrderFormValues) => {
    const payload: ServiceOrderPayload = {
      address: values.address.trim(),
      technician_id: values.technician_id || null,
      scheduled_start_at: fromFormDateTime(values.scheduled_start_at),
      scheduled_end_at: fromFormDateTime(values.scheduled_end_at),
      description: values.description?.trim() || null,
      items: [
        {
          service_id: values.service_id,
          quantity: 1,
          unit_amount_cents: service?.amount_cents ?? 0,
        },
      ],
    }

    setAttachmentsError(null)

    try {
      await onSubmit(payload, attachments)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        const attachmentError = Object.entries(error.fieldErrors).find(([field]) =>
          field.startsWith('attachments'),
        )
        const itemError = Object.entries(error.fieldErrors).find(([field]) =>
          field.startsWith('items'),
        )

        if (attachmentError) {
          setAttachmentsError(attachmentError[1]?.[0] ?? 'Revise o documento enviado.')
        }

        if (itemError) {
          form.setError('service_id', { message: itemError[1]?.[0] ?? 'Revise o serviço.' })
        }

        if (error.fieldErrors.scheduled_start_at || error.fieldErrors.technician_id) {
          form.setError('scheduled_start_at', {
            message:
              error.fieldErrors.scheduled_start_at?.[0] ??
              error.fieldErrors.technician_id?.[0] ??
              'Revise o horário.',
          })
        }

        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-9">
          <FormStep
            step={1}
            title="Endereço e serviço"
            description="Informe onde será o atendimento e qual serviço será executado. Com isso, calculamos os horários disponíveis.">
            <div className="grid gap-4 lg:grid-cols-3">
              <AddressAutocompleteField
                name="address"
                label="Endereço"
                required
                className="lg:col-span-2"
                placeholder="Digite ao menos 2 palavras do endereço"
                hint="Selecione uma sugestão para gravar o endereço completo."
              />
              <SearchSelectField
                name="service_id"
                label="Serviço"
                required
                placeholder="Buscar serviço..."
                emptyMessage="Nenhum serviço encontrado"
                loadOptions={loadServiceOptions}
                onSelectOption={(option) => {
                  setService(option as ServiceOption)
                  clearSchedule()
                }}
              />
            </div>
          </FormStep>

          <FormStep
            step={2}
            title="Horário"
            description="Escolha um dos horários livres. O cálculo considera a jornada, o deslocamento e os atendimentos já agendados.">
            <ServiceOrderSchedulingPanel
              serviceId={serviceId}
              technicianLoading={technician.isLoading}
            />
          </FormStep>

          <FormStep
            step={3}
            title="Descrição e documento"
            description="Descreva o atendimento e anexe o documento, se houver.">
            <TextareaField
              name="description"
              label="Descrição"
              rows={6}
              maxLength={5000}
              placeholder="Descreva o atendimento, observações para o técnico, referências do local..."
            />
            <AttachmentDropzone
              files={attachments}
              onChange={(files) => {
                setAttachmentsError(null)
                setAttachments(files)
              }}
              error={attachmentsError}
              disabled={submitting}
            />
          </FormStep>

          <div className="sticky bottom-0 z-20 -mx-5 -mb-5 rounded-b-xl bg-surface px-5 py-4 shadow-[0_-12px_24px_-18px_rgb(15_18_25/0.45)]">
            <div className="flex flex-col gap-2">
              {formHasErrors && (
                <p className="text-xs text-danger" role="alert">
                  Revise os campos destacados antes de salvar.
                </p>
              )}
              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <ButtonLink to="/service-orders" variant="secondary" className="w-full sm:w-auto">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={submitting} className="w-full sm:w-auto">
                  Criar OS
                </Button>
              </div>
            </div>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
