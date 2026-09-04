import { Plus, Trash2, AlertTriangle, CheckCircle2, Clock, XCircle } from 'lucide-react'
import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import {
  Badge,
  Button,
  ButtonLink,
  Card,
  CardContent,
  CardHeader,
  CheckboxField,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  Section,
  SelectField,
  Skeleton,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { formatDate, formatRelative } from '@/shared/utils/format'
import type { WebhookLog } from '@/shared/types/models'
import { useUpdateWebhook, useWebhookLogsQuery, useWebhookQuery, useWebhookEventsQuery } from '../hooks/useWebhooks'
import {
  webhookSchema,
  WEBHOOK_METHODS,
  type WebhookFormValues,
} from '../schemas/webhook.schema'

export default function WebhookEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const updateWebhook = useUpdateWebhook()
  const { data: webhook, isPending } = useWebhookQuery(Number(id))
  const { data: logs, isPending: logsPending } = useWebhookLogsQuery(Number(id))
  const { data: events = [] } = useWebhookEventsQuery()
  const [expandedLog, setExpandedLog] = useState<number | null>(null)

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

  useEffect(() => {
    if (!webhook) return
    form.reset({
      name: webhook.name,
      url: webhook.url,
      method: webhook.method,
      event: webhook.event,
      headers: webhook.headers
        ? Object.entries(webhook.headers).map(([key, value]) => ({ key, value }))
        : [],
      query_params: webhook.query_params
        ? Object.entries(webhook.query_params).map(([key, value]) => ({ key, value }))
        : [],
      body_template: webhook.body_template ? JSON.stringify(webhook.body_template, null, 2) : '',
      is_active: webhook.is_active,
      secret: '',
      description: webhook.description ?? '',
    })
  }, [webhook, form])

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

      await updateWebhook.mutateAsync({ id: Number(id), ...payload })
      navigate('/webhooks')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  if (isPending) return <Loading />

  return (
    <Page>
      <PageHeader
        title="Editar webhook"
        description={`Editando configurações de ${webhook?.name ?? '...'}`}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Webhooks', to: '/webhooks' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={onSubmit} className="space-y-8">
              <Section title="Identificação">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="name" label="Nome" required className="sm:col-span-2" />
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

              <Section title="Headers">
                <div className="space-y-2">
                  {headersArray.fields.map((field, index) => (
                    <div key={field.id} className="flex items-start gap-2">
                      <TextField name={`headers.${index}.key`} label="Nome" className="flex-1" />
                      <TextField name={`headers.${index}.value`} label="Valor" className="flex-1" />
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

              <Section title="Query params">
                <div className="space-y-2">
                  {paramsArray.fields.map((field, index) => (
                    <div key={field.id} className="flex items-start gap-2">
                      <TextField name={`query_params.${index}.key`} label="Nome" className="flex-1" />
                      <TextField name={`query_params.${index}.value`} label="Valor" className="flex-1" />
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

              <Section title="Body customizado" description="Template JSON com placeholders {{campo}} para os dados do recurso.">
                <TextareaField name="body_template" label="Template JSON" placeholder='{"title": "{{title}}", "slug": "{{slug}}"}' rows={4} />
              </Section>

              <Section title="Segurança">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="secret" label="Assinatura secreta" placeholder="Deixe em branco para manter a atual" hint="Será enviada como header X-Webhook-Signature (HMAC-SHA256)" />
                  <CheckboxField name="is_active" label="Ativo" />
                </div>
              </Section>

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <ButtonLink to="/webhooks" variant="secondary">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={updateWebhook.isPending}>
                  Salvar
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>

        <Card className="mt-6">
          <CardHeader title="Histórico de execuções" description="Últimas 50 tentativas de envio do webhook." />
          <CardContent className="p-0">
            {logsPending ? (
              <div className="space-y-3 p-5">
                {Array.from({ length: 3 }).map((_, i) => (
                  <Skeleton key={i} className="h-16 w-full" />
                ))}
              </div>
            ) : !logs || logs.length === 0 ? (
              <p className="p-5 text-sm text-muted">Nenhuma execução registrada ainda.</p>
            ) : (
              <div className="divide-y divide-surface-2">
                {logs.map((log) => (
                  <LogEntry
                    key={log.id}
                    log={log}
                    expanded={expandedLog === log.id}
                    onToggle={() => setExpandedLog(expandedLog === log.id ? null : log.id)}
                  />
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}

function LogEntry({
  log,
  expanded,
  onToggle,
}: {
  log: WebhookLog
  expanded: boolean
  onToggle: () => void
}) {
  const isSuccess = log.status_code !== null && log.status_code >= 200 && log.status_code < 300
  const isError = log.error_message !== null || (log.status_code !== null && log.status_code >= 400)

  return (
    <div>
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center gap-3 px-5 py-3 text-left transition-colors hover:bg-surface-2/40"
      >
        {isSuccess ? (
          <CheckCircle2 className="size-4 shrink-0 text-success" />
        ) : isError ? (
          <XCircle className="size-4 shrink-0 text-danger" />
        ) : (
          <AlertTriangle className="size-4 shrink-0 text-warning" />
        )}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <Badge variant={isSuccess ? 'success' : isError ? 'danger' : 'warning'} className="text-[11px]">
              {log.status_code ?? 'ERRO'}
            </Badge>
            <span className="text-xs text-muted">
              <Clock className="mr-1 inline size-3" />
              {log.duration_ms !== null ? `${log.duration_ms}ms` : '-'}
            </span>
          </div>
          <p className="mt-0.5 truncate text-xs text-muted">
            {log.error_message ?? `${formatRelative(log.created_at!)}`}
          </p>
        </div>
      </button>
      {expanded && (
        <div className="space-y-3 bg-surface-2/30 px-5 py-4">
          {log.error_message && (
            <div>
              <p className="mb-1 text-xs font-medium text-danger">Erro</p>
              <pre className="whitespace-pre-wrap rounded-lg bg-surface p-2.5 text-xs text-foreground">
                {log.error_message}
              </pre>
            </div>
          )}
          {log.request_payload && (
            <div>
              <p className="mb-1 text-xs font-medium text-muted">Payload enviado</p>
              <pre className="max-h-48 overflow-auto rounded-lg bg-surface p-2.5 text-xs text-foreground">
                {JSON.stringify(log.request_payload, null, 2)}
              </pre>
            </div>
          )}
          {log.response_body && (
            <div>
              <p className="mb-1 text-xs font-medium text-muted">Resposta</p>
              <pre className="max-h-48 overflow-auto rounded-lg bg-surface p-2.5 text-xs text-foreground">
                {log.response_body}
              </pre>
            </div>
          )}
          <p className="text-xs text-muted">
            Executado em {log.created_at ? formatDate(log.created_at) : '-'}
          </p>
        </div>
      )}
    </div>
  )
}
