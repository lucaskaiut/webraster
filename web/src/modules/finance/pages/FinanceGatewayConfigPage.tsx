import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import {
  Button,
  Card,
  CardContent,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  Section,
  SelectField,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { usePaymentGatewayConfigQuery, useUpdatePaymentGatewayConfig } from '../hooks/useFinance'
import type { GatewayCredentialField } from '@/shared/types/models'

interface GatewayConfigFormValues {
  gateway: string
  is_active: boolean
  credentials: Record<string, string>
}

function emptyCredentials(schema: GatewayCredentialField[]): Record<string, string> {
  return Object.fromEntries(schema.map((field) => [field.name, '']))
}

export default function FinanceGatewayConfigPage() {
  const query = usePaymentGatewayConfigQuery()
  const update = useUpdatePaymentGatewayConfig()

  const form = useForm<GatewayConfigFormValues>({
    defaultValues: {
      gateway: 'asaas',
      is_active: false,
      credentials: {},
    },
  })

  const schema = query.data?.credential_schema ?? []

  useEffect(() => {
    if (!query.data) return
    form.reset({
      gateway: query.data.gateway,
      is_active: query.data.is_active,
      credentials: {
        ...emptyCredentials(query.data.credential_schema),
        environment: String(query.data.credentials.environment ?? 'sandbox'),
      },
    })
  }, [query.data, form])

  const onSubmit = async (values: GatewayConfigFormValues) => {
    try {
      const credentials = Object.fromEntries(
        Object.entries(values.credentials).filter(([, value]) => value.trim() !== ''),
      )
      await update.mutateAsync({
        gateway: values.gateway,
        is_active: values.is_active,
        credentials,
      })
      void query.refetch()
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Page>
      <PageHeader
        title="Gateway de pagamento"
        description="Credenciais do provedor usado para cobrar clientes deste tenant."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Gateway' },
        ]}
      />
      <PageContent>
        {query.isPending && <Loading />}
        {!query.isPending && query.data && (
          <Card>
            <CardContent>
              <Form form={form} onSubmit={onSubmit} className="space-y-8">
                <Section title="Provedor">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <SelectField
                      name="gateway"
                      label="Gateway"
                      options={(query.data.gateways ?? []).map((item) => ({
                        value: item.key,
                        label: item.label,
                      }))}
                    />
                    <SwitchField name="is_active" label="Integração ativa" />
                  </div>
                  <p className="text-sm text-muted">
                    URL do webhook: <span className="break-all font-mono">{query.data.webhook_url}</span>
                  </p>
                </Section>
                <Section title="Credenciais">
                  <div className="grid gap-4 sm:grid-cols-2">
                    {schema.map((field) => (
                      <CredentialField
                        key={field.name}
                        field={field}
                        hint={credentialHint(field, query.data?.credentials)}
                      />
                    ))}
                  </div>
                </Section>
                <div className="flex justify-end">
                  <Button type="submit" loading={update.isPending}>
                    Salvar configuração
                  </Button>
                </div>
              </Form>
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}

function CredentialField({
  field,
  hint,
}: {
  field: GatewayCredentialField
  hint?: string
}) {
  if (field.type === 'select') {
    return (
      <SelectField
        name={`credentials.${field.name}`}
        label={field.label}
        options={field.options ?? []}
        hint={hint ?? field.hint}
      />
    )
  }

  if (field.type === 'boolean') {
    return <SwitchField name={`credentials.${field.name}`} label={field.label} hint={hint ?? field.hint} />
  }

  return (
    <TextField
      name={`credentials.${field.name}`}
      label={field.label}
      type={field.secret || field.type === 'password' ? 'password' : 'text'}
      className="sm:col-span-2"
      hint={hint ?? field.hint}
    />
  )
}

function credentialHint(
  field: GatewayCredentialField,
  credentials: Record<string, unknown> | undefined,
): string | undefined {
  if (field.name === 'api_key' && credentials?.has_api_key) {
    return `Chave atual: ${String(credentials.api_key_masked ?? '••••')}. Deixe em branco para manter.`
  }
  if (field.name === 'webhook_token' && credentials?.has_webhook_token) {
    return 'Token já configurado. Deixe em branco para manter.'
  }
  return field.hint
}
