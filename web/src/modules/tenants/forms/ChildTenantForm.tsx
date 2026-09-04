import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ButtonLink, Card, CardContent, Form, Section, SelectField, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { usePlansQuery } from '@/modules/billing/hooks/useBilling'
import type { CreateChildTenantPayload } from '../services/tenants.service'
import { childTenantSchema, type ChildTenantFormValues } from '../schemas/tenant.schema'

export function ChildTenantForm({
  submitting,
  onSubmit,
}: {
  submitting: boolean
  onSubmit: (payload: CreateChildTenantPayload) => Promise<unknown>
}) {
  const { data: plans } = usePlansQuery()

  const form = useForm<ChildTenantFormValues>({
    resolver: zodResolver(childTenantSchema),
    defaultValues: {
      tenant: { name: '', document: '', email: '', phone: '', domain: '' },
      user: { name: '', email: '', password: '' },
      plan_id: null,
    },
  })

  const handleSubmit = async (values: ChildTenantFormValues) => {
    const payload: CreateChildTenantPayload = {
      tenant: {
        name: values.tenant.name,
        document: onlyDigits(values.tenant.document),
        email: values.tenant.email,
        phone: values.tenant.phone,
        domain: values.tenant.domain,
      },
      user: {
        name: values.user.name,
        email: values.user.email,
        password: values.user.password,
      },
      plan_id: values.plan_id || null,
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
          <Section title="Dados da empresa">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="tenant.name" label="Nome" required className="sm:col-span-2" />
              <TextField name="tenant.document" label="CPF / CNPJ" required placeholder="Somente números" />
              <TextField name="tenant.email" label="E-mail" type="email" required />
              <TextField name="tenant.phone" label="Telefone" required placeholder="(41) 99999-9999" />
              <TextField name="tenant.domain" label="Domínio" required placeholder="empresa.com.br" />
            </div>
          </Section>

          <Section
            title="Administrador"
            description="Credenciais do usuário que administrará esta empresa."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="user.name" label="Nome" required className="sm:col-span-2" />
              <TextField name="user.email" label="E-mail" type="email" required />
              <TextField
                name="user.password"
                label="Senha"
                type="password"
                autoComplete="new-password"
                required
                hint="Mínimo de 8 caracteres"
              />
            </div>
          </Section>

          <Section
            title="Plano (opcional)"
            description="Associe um plano à empresa. Sem plano, ela não poderá acessar o sistema."
          >
            <SelectField
              name="plan_id"
              label="Plano"
              options={(plans ?? []).map((plan) => ({ value: plan.id, label: plan.name }))}
              placeholder="Sem plano"
            />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/tenants" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              Criar empresa
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
