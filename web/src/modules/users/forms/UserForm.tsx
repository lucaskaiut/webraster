import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ButtonLink, Card, CardContent, Form, Section, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { RolesField } from '../components/RolesField'
import type { UserPayload } from '../services/users.service'
import { createUserSchema, updateUserSchema, type UserFormValues } from '../schemas/user.schema'

interface UserFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<UserFormValues>
  submitting: boolean
  onSubmit: (payload: UserPayload) => Promise<unknown>
}

export function UserForm({ mode, defaultValues, submitting, onSubmit }: UserFormProps) {
  const form = useForm<UserFormValues>({
    resolver: zodResolver(mode === 'create' ? createUserSchema : updateUserSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      document: '',
      password: '',
      password_confirmation: '',
      role_ids: [],
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: UserFormValues) => {
    const payload: UserPayload = {
      name: values.name,
      email: values.email,
      phone: values.phone || null,
      document: values.document ? onlyDigits(values.document) : null,
      role_ids: values.role_ids,
    }

    if (values.password) {
      payload.password = values.password
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
          <Section title="Informações básicas">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome completo" required className="sm:col-span-2" />
              <TextField name="email" label="E-mail" type="email" required />
              <TextField name="phone" label="Telefone" placeholder="(41) 99999-9999" />
              <TextField name="document" label="CPF" placeholder="Somente números" />
            </div>
          </Section>

          <Section
            title="Perfis de acesso"
            description="Selecione um ou mais perfis para definir o que este usuário pode fazer no sistema."
          >
            <RolesField />
          </Section>

          <Section
            title={mode === 'create' ? 'Credenciais de acesso' : 'Alterar senha'}
            description={
              mode === 'edit' ? 'Preencha apenas se desejar definir uma nova senha.' : undefined
            }
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField
                name="password"
                label="Senha"
                type="password"
                autoComplete="new-password"
                hint="Mínimo de 8 caracteres"
                required={mode === 'create'}
              />
              <TextField
                name="password_confirmation"
                label="Confirmar senha"
                type="password"
                autoComplete="new-password"
                required={mode === 'create'}
              />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/users" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar usuário' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
