import { useNavigate } from 'react-router'
import { useForm } from 'react-hook-form'
import {
  Button,
  Card,
  CardContent,
  Form,
  Page,
  PageContent,
  PageHeader,
  SearchSelectField,
  SelectField,
  SwitchField,
  TextField,
  TextareaField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { toast } from '@/shared/stores/toast.store'
import { applyApiErrorsToForm, formResolver } from '@/shared/utils/forms'
import { clientsService } from '@/modules/clients/services/clients.service'
import { usersService } from '@/modules/users/services/users.service'
import { useSendNotification } from '../hooks/useNotifications'
import { notificationSchema, type NotificationFormValues } from '../schemas/notification.schema'

async function loadClientOptions(search: string) {
  const response = await clientsService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((client) => ({ value: client.id, label: client.name }))
}

async function resolveClientLabel(value: string) {
  try {
    const client = await clientsService.get(value)
    return { value: client.id, label: client.name }
  } catch {
    return null
  }
}

async function loadUserOptions(search: string) {
  const response = await usersService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((user) => ({
    value: user.id,
    label: `${user.name} (${user.email})`,
  }))
}

async function resolveUserLabel(value: string) {
  try {
    const user = await usersService.get(value)
    return { value: user.id, label: `${user.name} (${user.email})` }
  } catch {
    return null
  }
}

export default function NotificationSendPage() {
  const navigate = useNavigate()
  const send = useSendNotification()

  const form = useForm<NotificationFormValues>({
    resolver: formResolver<NotificationFormValues>(notificationSchema),
    defaultValues: {
      title: '',
      body: '',
      audience: 'tenant',
      client_id: '',
      user_id: '',
      push: true,
    },
  })

  const audience = form.watch('audience')

  const handleSubmit = async (values: NotificationFormValues) => {
    try {
      const result = await send.mutateAsync({
        title: values.title,
        body: values.body,
        audience: values.audience === 'user' ? 'users' : values.audience,
        client_id: values.audience === 'client' ? values.client_id : null,
        user_ids: values.audience === 'user' && values.user_id ? [values.user_id] : undefined,
        push: values.push,
      })

      toast.success(`Notificação enviada para ${result.recipients} usuário(s)`)
      navigate('/notifications/sent')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
        return
      }

      toast.error('Não foi possível enviar a notificação')
    }
  }

  return (
    <Page>
      <PageHeader
        title="Enviar notificação"
        description="Notificação manual para a equipe ou para os usuários de um cliente (in-app + push)."
        breadcrumb={[{ label: 'Notificações', to: '/notifications/sent' }, { label: 'Enviar' }]}
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={handleSubmit} className="space-y-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  name="title"
                  label="Título"
                  required
                  className="sm:col-span-2"
                  placeholder="Ex.: Manutenção programada"
                />

                <TextareaField
                  name="body"
                  label="Mensagem"
                  required
                  className="sm:col-span-2"
                  placeholder="Escreva o conteúdo da notificação..."
                />

                <SelectField
                  name="audience"
                  label="Destinatários"
                  required
                  options={[
                    { value: 'tenant', label: 'Equipe (todos do tenant)' },
                    { value: 'client', label: 'Clientes de um cliente' },
                    { value: 'user', label: 'Usuário específico' },
                  ]}
                />

                {audience === 'client' && (
                  <SearchSelectField
                    name="client_id"
                    label="Cliente"
                    required
                    placeholder="Buscar cliente..."
                    emptyMessage="Nenhum cliente encontrado"
                    loadOptions={loadClientOptions}
                    resolveLabel={resolveClientLabel}
                  />
                )}

                {audience === 'user' && (
                  <SearchSelectField
                    name="user_id"
                    label="Usuário"
                    required
                    placeholder="Buscar usuário..."
                    emptyMessage="Nenhum usuário encontrado"
                    loadOptions={loadUserOptions}
                    resolveLabel={resolveUserLabel}
                  />
                )}

                <SwitchField
                  name="push"
                  label="Enviar push"
                  hint="Além da caixa de notificações no app/painel."
                  className="sm:col-span-2"
                />
              </div>

              <div className="flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={() => navigate(-1)}>
                  Cancelar
                </Button>
                <Button type="submit" loading={send.isPending}>
                  Enviar notificação
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
