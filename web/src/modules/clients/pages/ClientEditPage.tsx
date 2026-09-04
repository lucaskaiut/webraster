import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useNavigate, useParams } from 'react-router'
import { Contact, Plus, Trash2, UserX } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Form,
  Page,
  PageContent,
  PageHeader,
  Section,
  Skeleton,
  TextField,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import type { User } from '@/shared/types/models'
import { RolesField } from '@/modules/users/components/RolesField'
import { ClientForm } from '../forms/ClientForm'
import {
  useClientQuery,
  useClientUsersQuery,
  useCreateClientUser,
  useDeleteClientUser,
  useUpdateClient,
} from '../hooks/useClients'
import {
  createClientUserSchema,
  type ClientUserFormValues,
} from '../schemas/client.schema'

function FormSkeleton() {
  return (
    <Card>
      <CardContent className="space-y-5">
        <Skeleton className="h-4 w-40" />
        <div className="grid gap-4 sm:grid-cols-2">
          <Skeleton className="h-10 sm:col-span-2" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
          <Skeleton className="h-10" />
        </div>
        <div className="flex justify-end gap-2">
          <Skeleton className="h-10 w-24" />
          <Skeleton className="h-10 w-36" />
        </div>
      </CardContent>
    </Card>
  )
}

function ClientUsersSection({ clientId }: { clientId: string }) {
  const [userToDelete, setUserToDelete] = useState<User | null>(null)
  const usersQuery = useClientUsersQuery(clientId, { per_page: 50 })
  const createUser = useCreateClientUser(clientId)
  const deleteUser = useDeleteClientUser(clientId)

  const form = useForm<ClientUserFormValues>({
    resolver: zodResolver(createClientUserSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      document: '',
      password: '',
      role_ids: [],
    },
  })

  const handleCreate = async (values: ClientUserFormValues) => {
    try {
      await createUser.mutateAsync({
        name: values.name,
        email: values.email,
        phone: values.phone || null,
        document: values.document ? onlyDigits(values.document) : null,
        password: values.password,
        ...(values.role_ids.length > 0 ? { role_ids: values.role_ids } : {}),
      })
      form.reset({
        name: '',
        email: '',
        phone: '',
        document: '',
        password: '',
        role_ids: [],
      })
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const columns: Array<Column<User>> = [
    {
      key: 'name',
      header: 'Usuário',
      render: (user) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{user.name}</p>
          <p className="truncate text-[13px] text-muted">{user.email}</p>
        </div>
      ),
    },
    {
      key: 'phone',
      header: 'Telefone',
      render: (user) => <span className="text-muted">{user.phone ?? '—'}</span>,
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-16 text-right',
      render: (user) => (
        <div className="flex justify-end">
          <Can permission={Permission.CLIENT_UPDATE}>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setUserToDelete(user)}
              aria-label={`Excluir ${user.name}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          </Can>
        </div>
      ),
    },
  ]

  return (
    <>
      <Card>
        <CardContent className="space-y-8">
          <Section
            title="Usuários do cliente"
            description="Contas de acesso vinculadas a este cliente. Se nenhum perfil for selecionado, a API atribui o perfil Cliente."
          >
            <DataTable
              caption="Usuários do cliente"
              columns={columns}
              rows={usersQuery.data?.data ?? []}
              rowKey={(user) => user.id}
              loading={usersQuery.isPending}
              emptyState={
                <EmptyState
                  icon={Contact}
                  title="Nenhum usuário vinculado"
                  description="Cadastre o primeiro usuário para este cliente."
                />
              }
            />
          </Section>

          <Can permission={Permission.CLIENT_UPDATE}>
            <Section title="Novo usuário" description="Crie um acesso para este cliente.">
              <Form form={form} onSubmit={handleCreate} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="name" label="Nome" required className="sm:col-span-2" />
                  <TextField name="email" label="E-mail" type="email" required />
                  <TextField
                    name="password"
                    label="Senha"
                    type="password"
                    autoComplete="new-password"
                    hint="Mínimo de 8 caracteres"
                    required
                  />
                  <TextField name="phone" label="Telefone" placeholder="(41) 99999-9999" />
                  <TextField name="document" label="CPF" placeholder="Somente números" />
                </div>

                <RolesField />

                <div className="flex justify-end">
                  <Button type="submit" loading={createUser.isPending}>
                    <Plus className="size-4" />
                    Adicionar usuário
                  </Button>
                </div>
              </Form>
            </Section>
          </Can>
        </CardContent>
      </Card>

      <ConfirmDialog
        open={userToDelete !== null}
        onClose={() => setUserToDelete(null)}
        onConfirm={() => {
          if (!userToDelete) return
          deleteUser.mutate(userToDelete.id, { onSettled: () => setUserToDelete(null) })
        }}
        loading={deleteUser.isPending}
        title="Excluir usuário do cliente"
        description={
          <>
            Tem certeza que deseja excluir <strong>{userToDelete?.name}</strong>? Esta ação não pode
            ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </>
  )
}

export default function ClientEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useClientQuery(id)
  const updateClient = useUpdateClient(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar cliente"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Clientes', to: '/clients' },
          { label: 'Editar' },
        ]}
      />

      <PageContent className="space-y-6">
        {query.isPending && <FormSkeleton />}

        {query.isError && (
          <Card>
            <EmptyState
              icon={UserX}
              title="Cliente não encontrado"
              description="O cliente pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/clients" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <>
            <ClientForm
              mode="edit"
              defaultValues={{
                name: query.data.name,
                document: query.data.document,
                email: query.data.email ?? '',
                phone: query.data.phone ?? '',
                street: query.data.street ?? '',
                number: query.data.number ?? '',
                complement: query.data.complement ?? '',
                neighborhood: query.data.neighborhood ?? '',
                city: query.data.city ?? '',
                state: query.data.state ?? '',
                zip: query.data.zip ?? '',
                is_active: query.data.is_active,
              }}
              submitting={updateClient.isPending}
              onSubmit={async (payload) => {
                await updateClient.mutateAsync(payload)
                navigate('/clients')
              }}
            />

            {id && <ClientUsersSection clientId={id} />}
          </>
        )}
      </PageContent>
    </Page>
  )
}
