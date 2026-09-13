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
import { useUpdateVehicleDataConfig, useVehicleDataConfigQuery } from '../hooks/useVehicleData'

interface VehicleDataConfigFormValues {
  provider: string
  is_active: boolean
  credentials: Record<string, string>
}

export default function VehicleDataConfigPage() {
  const query = useVehicleDataConfigQuery()
  const update = useUpdateVehicleDataConfig()

  const form = useForm<VehicleDataConfigFormValues>({
    defaultValues: {
      provider: '',
      is_active: false,
      credentials: {},
    },
  })

  useEffect(() => {
    if (!query.data) return
    form.reset({
      provider: query.data.provider,
      is_active: query.data.is_active,
      credentials: Object.fromEntries(
        query.data.credential_schema.map((field) => [field.name, '']),
      ),
    })
  }, [query.data, form])

  const onSubmit = async (values: VehicleDataConfigFormValues) => {
    try {
      const credentials = Object.fromEntries(
        Object.entries(values.credentials).filter(([, value]) => value.trim() !== ''),
      )
      await update.mutateAsync({
        provider: values.provider,
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
        title="Consulta de placa"
        description="Credenciais do provedor usado para consultar dados veiculares por placa neste tenant."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Veículos', to: '/vehicles' },
          { label: 'Consulta de placa' },
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
                      name="provider"
                      label="Provedor"
                      options={(query.data.providers ?? []).map((item) => ({
                        value: item.key,
                        label: item.label,
                      }))}
                    />
                    <SwitchField name="is_active" label="Integração ativa" />
                  </div>
                </Section>
                <Section title="Credenciais">
                  <div className="grid gap-4 sm:grid-cols-2">
                    {query.data.credential_schema.map((field) => (
                      <TextField
                        key={field.name}
                        name={`credentials.${field.name}`}
                        label={field.label}
                        type={field.secret || field.type === 'password' ? 'password' : 'text'}
                        className="sm:col-span-2"
                        hint={credentialHint(field.name, query.data.credentials)}
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

function credentialHint(
  fieldName: string,
  credentials: Record<string, unknown>,
): string | undefined {
  if (fieldName === 'token' && credentials?.has_token) {
    return `Token atual: ${String(credentials.token_masked ?? '••••')}. Deixe em branco para manter.`
  }
  return undefined
}
