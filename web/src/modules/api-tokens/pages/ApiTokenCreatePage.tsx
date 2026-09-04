import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate } from 'react-router'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Page,
  PageContent,
  PageHeader,
  RadioGroupField,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { PermissionsField } from '@/modules/roles/components/PermissionsField'
import { useCreateApiToken } from '../hooks/useApiTokens'
import {
  apiTokenSchema,
  EXPIRATION_OPTIONS,
  resolveExpiresAt,
  type ApiTokenFormValues,
} from '../schemas/api-token.schema'
import { TokenSecretModal } from '../components/TokenSecretModal'

export default function ApiTokenCreatePage() {
  const navigate = useNavigate()
  const createToken = useCreateApiToken()
  const [issuedToken, setIssuedToken] = useState<string | null>(null)

  const form = useForm<ApiTokenFormValues>({
    resolver: zodResolver(apiTokenSchema),
    defaultValues: { name: '', expiration: 'never', permissions: [] },
  })

  const onSubmit = async (values: ApiTokenFormValues) => {
    try {
      const issued = await createToken.mutateAsync({
        name: values.name,
        expires_at: resolveExpiresAt(values.expiration),
        permissions: values.permissions.length > 0 ? values.permissions : null,
      })

      setIssuedToken(issued.token)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Page>
      <PageHeader
        title="Novo token de API"
        description="Gere um token para integrações externas com a API."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Tokens de API', to: '/api-tokens' },
          { label: 'Novo token' },
        ]}
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={onSubmit} className="space-y-8">
              <Section title="Identificação e expiração">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name="name"
                    label="Nome do token"
                    placeholder="Ex.: Integração ERP"
                    hint="Use um nome que identifique onde o token será utilizado."
                    required
                    className="sm:col-span-2"
                  />
                  <RadioGroupField
                    name="expiration"
                    label="Expiração"
                    options={[...EXPIRATION_OPTIONS]}
                    required
                    className="sm:col-span-2"
                  />
                </div>
              </Section>

              <Section
                title="Permissões (escopos)"
                description="Se nenhuma permissão for selecionada, o token terá acesso irrestrito."
              >
                <PermissionsField name="permissions" />
              </Section>

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <ButtonLink to="/api-tokens" variant="secondary">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={createToken.isPending}>
                  Gerar token
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>

      <TokenSecretModal
        token={issuedToken}
        open={issuedToken !== null}
        onClose={() => {
          setIssuedToken(null)
          navigate('/api-tokens')
        }}
      />
    </Page>
  )
}
