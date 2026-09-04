import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { RolePayload } from '../services/roles.service'
import { roleSchema, type RoleFormValues } from '../schemas/role.schema'
import { PermissionsField } from '../components/PermissionsField'

interface RoleFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<RoleFormValues>
  submitting: boolean
  onSubmit: (payload: RolePayload) => Promise<unknown>
}

export function RoleForm({ mode, defaultValues, submitting, onSubmit }: RoleFormProps) {
  const form = useForm<RoleFormValues>({
    resolver: zodResolver(roleSchema),
    defaultValues: {
      name: '',
      description: '',
      permissions: [],
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: RoleFormValues) => {
    try {
      await onSubmit({
        name: values.name,
        description: values.description || null,
        permissions: values.permissions,
      })
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
          <Section title="Identificação">
            <div className="grid gap-4">
              <TextField name="name" label="Nome do perfil" placeholder="Ex.: Suporte" required />
              <TextareaField
                name="description"
                label="Descrição"
                placeholder="Descreva a finalidade deste perfil..."
                rows={3}
              />
            </div>
          </Section>

          <Section
            title="Permissões de acesso"
            description="Selecione o que os usuários com este perfil podem fazer."
          >
            <PermissionsField />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/roles" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar perfil' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
