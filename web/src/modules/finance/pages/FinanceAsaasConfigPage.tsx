import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
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
import { useAsaasConfigQuery, useUpdateAsaasConfig } from '../hooks/useFinance'
import { ASAAS_ENVIRONMENT_OPTIONS } from '../lib/labels'
import { asaasConfigSchema, type AsaasConfigFormValues } from '../schemas/asaas-config.schema'

export default function FinanceAsaasConfigPage() {
  const query = useAsaasConfigQuery()
  const update = useUpdateAsaasConfig()

  const form = useForm<AsaasConfigFormValues>({
    resolver: zodResolver(asaasConfigSchema),
    defaultValues: {
      environment: 'sandbox',
      api_key: '',
      webhook_token: '',
      is_active: false,
    },
  })

  useEffect(() => {
    if (!query.data) return
    form.reset({
      environment: query.data.environment ?? 'sandbox',
      api_key: '',
      webhook_token: '',
      is_active: query.data.is_active,
    })
  }, [query.data, form])

  const onSubmit = async (values: AsaasConfigFormValues) => {
    try {
      await update.mutateAsync({
        environment: values.environment,
        is_active: values.is_active,
        ...(values.api_key.trim() ? { api_key: values.api_key.trim() } : {}),
        ...(values.webhook_token.trim()
          ? { webhook_token: values.webhook_token.trim() }
          : {}),
      })
      form.setValue('api_key', '')
      form.setValue('webhook_token', '')
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
        title="Configuração Asaas"
        description="API key e ambiente do gateway de pagamento do tenant."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Asaas' },
        ]}
      />
      <PageContent>
        {query.isPending && <Loading />}
        {!query.isPending && (
          <Card>
            <CardContent>
              <Form form={form} onSubmit={onSubmit} className="space-y-8">
                <Section title="Gateway">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <SelectField
                      name="environment"
                      label="Ambiente"
                      options={ASAAS_ENVIRONMENT_OPTIONS}
                    />
                    <SwitchField name="is_active" label="Integração ativa" />
                    <TextField
                      name="api_key"
                      label="API key"
                      type="password"
                      className="sm:col-span-2"
                      hint={
                        query.data?.has_api_key
                          ? `Chave atual: ${query.data.api_key_masked ?? '••••'}. Deixe em branco para manter.`
                          : 'Informe a API key do Asaas.'
                      }
                    />
                    <TextField
                      name="webhook_token"
                      label="Token do webhook"
                      type="password"
                      className="sm:col-span-2"
                      hint={
                        query.data?.has_webhook_token
                          ? 'Token já configurado. Deixe em branco para manter.'
                          : 'Token usado para validar webhooks do Asaas.'
                      }
                    />
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
