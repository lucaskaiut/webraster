import { Plus, Trash2 } from 'lucide-react'
import { useFieldArray, useForm } from 'react-hook-form'
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
import { useCreateWebhook, useWebhookEventsQuery } from '../hooks/useWebhooks'
import {
  webhookSchema,
  WEBHOOK_METHODS,
  type WebhookFormValues,
} from '../schemas/webhook.schema'

export default function WebhookCreatePage() {
  const navigate = useNavigate()
  const createWebhook = useCreateWebhook()
  const { data: events = [] } = useWebhookEventsQuery()

  const form = useForm<WebhookFormValues>({
    resolver: zodResolver(webhookSchema),
    defaultValues: {
      name: '',
      url: '',
      method: 'POST',
      event: '',
      headers: [],
      query_params: [],
      body_template: '',
      is_active: true,
      secret: '',
      description: '',
    },
  })

  const headersArray = useFieldArray({ control: form.control, name: 'headers' })
  const paramsArray = useFieldArray({ control: form.control, name: 'query_params' })

  const onSubmit = async (values: WebhookFormValues) => {
    try {
      const payload = {
        name: values.name,
        url: values.url,
        method: values.method,
        event: values.event,
        headers:
          values.headers && values.headers.length > 0
            ? Object.fromEntries(values.headers.map((h) => [h.key, h.value]))
            : null,
        query_params:
          values.query_params && values.query_params.length > 0
            ? Object.fromEntries(values.query_params.map((p) => [p.key, p.value]))
            : null,
        body_template: values.body_template ? JSON.parse(values.body_template) : null,
        is_active: values.is_active,
        secret: values.secret || null,
        description: values.description || null,
      }

      await createWebhook.mutateAsync(payload)
      navigate('/webhooks')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Page>
      <PageHeader
        title="Novo webhook"
        description="Configure uma URL para receber notificações quando eventos ocorrerem."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Webhooks', to: '/webhooks' },
          { label: 'Novo webhook' },
        ]}
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={onSubmit} className="space-y-8">
              <Section title="Identificação">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="name" label="Nome" placeholder="Ex.: Notificar novo post" required className="sm:col-span-2" />
                  <TextareaField name="description" label="Descrição" placeholder="Breve descrição da finalidade do webhook" className="sm:col-span-2" />
                </div>
              </Section>

              <Section title="Evento e requisição">
                <div className="grid gap-4 sm:grid-cols-3">
                  <SelectField name="event" label="Evento" required placeholder="Selecione um evento" options={events} />
                  <SelectField name="method" label="Método HTTP" required options={[...WEBHOOK_METHODS]} />
                  <TextField name="url" label="URL" placeholder="https://exemplo.com/hooks" required className="sm:col-span-3" />
                </div>
              </Section>

              <Section title="Headers" description="Headers HTTP enviados na requisição.">
                <div className="space-y-2">
                  {headersArray.fields.map((field, index) => (
                    <div key={field.id} className="flex items-start gap-2">
                      <TextField name={`headers.${index}.key`} label="Nome" placeholder="Content-Type" className="flex-1" />
                      <TextField name={`headers.${index}.value`} label="Valor" placeholder="application/json" className="flex-1" />
                      <Button variant="ghost" size="sm" type="button" className="mt-7" onClick={() => headersArray.remove(index)} aria-label="Remover header">
                        <Trash2 className="size-4 text-danger" />
                      </Button>
                    </div>
                  ))}
                  <Button variant="ghost" size="sm" type="button" onClick={() => headersArray.append({ key: '', value: '' })}>
                    <Plus className="size-4" />
                    Adicionar header
                  </Button>
                </div>
              </Section>

              <Section title="Query params" description="Parâmetros anexados à URL.">
                <div className="space-y-2">
                  {paramsArray.fields.map((field, index) => (
                    <div key={field.id} className="flex items-start gap-2">
                      <TextField name={`query_params.${index}.key`} label="Nome" placeholder="token" className="flex-1" />
                      <TextField name={`query_params.${index}.value`} label="Valor" placeholder="abc123" className="flex-1" />
                      <Button variant="ghost" size="sm" type="button" className="mt-7" onClick={() => paramsArray.remove(index)} aria-label="Remover parâmetro">
                        <Trash2 className="size-4 text-danger" />
                      </Button>
                    </div>
                  ))}
                  <Button variant="ghost" size="sm" type="button" onClick={() => paramsArray.append({ key: '', value: '' })}>
                    <Plus className="size-4" />
                    Adicionar parâmetro
                  </Button>
                </div>
              </Section>

              <Section title="Body customizado" description="Template JSON com placeholders {{campo}} para os dados do recurso. Deixe vazio para usar o padrão.">
                <TextareaField name="body_template" label="Template JSON" placeholder='{"title": "{{title}}", "slug": "{{slug}}"}' rows={4} />
              </Section>

              <Section title="Segurança">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="secret" label="Assinatura secreta" placeholder="minha-chave-secreta" hint="Será enviada como header X-Webhook-Signature (HMAC-SHA256)" />
                  <CheckboxField name="is_active" label="Ativo" />
                </div>
              </Section>

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <ButtonLink to="/webhooks" variant="secondary">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={createWebhook.isPending}>
                  Criar webhook
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
