import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Contact, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Form,
  Section,
  TextField,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { User } from '@/shared/types/models'
import { RolesField } from '@/modules/users/components/RolesField'
import {
  useClientQuery,
  useClientUsersQuery,
  useCreateClientUser,
  useDeleteClientUser,
  useUpdateClientUser,
} from '../hooks/useClients'
import { createClientUserSchema, type ClientUserFormValues } from '../schemas/client.schema'
import { ClientUserEditModal } from './ClientUserEditModal'

export function ClientUsersSection({ clientId }: { clientId: string }) {
  const [userToDelete, setUserToDelete] = useState<User | null>(null)
  const [userToEdit, setUserToEdit] = useState<User | null>(null)
  const usersQuery = useClientUsersQuery(clientId, { per_page: 50 })
  const clientQuery = useClientQuery(clientId)
  const createUser = useCreateClientUser(clientId)
  const updateUser = useUpdateClientUser(clientId)
  const deleteUser = useDeleteClientUser(clientId)

  const form = useForm<ClientUserFormValues>({
    resolver: zodResolver(createClientUserSchema),
    defaultValues: {
      name: '',
      email: '',
      phone: '',
      password: '',
      role_ids: [],
    },
  })

  const handleCreate = async (values: ClientUserFormValues) => {
    try {
      await createUser.mutateAsync({
        name: values.name,
        email: values.email,
        phone: values.phone || undefined,
        password: values.password,
        ...(values.role_ids.length > 0 ? { role_ids: values.role_ids } : {}),
      })
      form.reset({
        name: '',
        email: '',
        phone: '',
        password: '',
        role_ids: [],
      })
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const users = usersQuery.data?.data ?? []
  const hasPrimaryUser = users.some((user) => user.is_primary)
  const client = clientQuery.data

  /**
   * Preenche automaticamente o formulário com os dados do cliente enquanto o
   * usuário principal ainda não existe (o e-mail do principal fica reservado
   * depois que ele é criado).
   */
  useEffect(() => {
    if (!client || !usersQuery.isSuccess || hasPrimaryUser) return

    form.reset({
      name: client.name,
      email: client.email ?? '',
      phone: client.phone ?? '',
      password: '',
      role_ids: [],
    })
  }, [client, usersQuery.isSuccess, hasPrimaryUser, form])

  const columns: Array<Column<User>> = [
    {
      key: 'name',
      header: 'Usuário',
      render: (user) => (
        <div className="min-w-0">
          <p className="flex items-center gap-2 truncate font-medium text-foreground">
            <span className="truncate">{user.name}</span>
            {user.is_primary && <Badge variant="primary">Principal</Badge>}
          </p>
          <p className="truncate text-[13px] text-muted">{user.email}</p>
        </div>
      ),
    },
    {
      key: 'roles',
      header: 'Perfil',
      render: (user) => (
        <div className="flex flex-wrap gap-1">
          {user.roles?.length ? (
            user.roles.map((role) => (
              <Badge key={role.id} variant="neutral">
                {role.name}
              </Badge>
            ))
          ) : (
            <span className="text-muted">—</span>
          )}
        </div>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-24 text-right',
      render: (user) => (
        <div className="flex justify-end gap-1">
          <Can permission={Permission.CLIENT_UPDATE}>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setUserToEdit(user)}
              aria-label={`Editar ${user.name}`}
            >
              <Pencil className="size-4" />
            </Button>
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
            title="Usuários vinculados"
            description="Contas de acesso deste cliente. O usuário principal é criado automaticamente com os dados do cadastro do cliente; se nenhum perfil for selecionado, a API atribui o perfil Cliente."
          >
            <DataTable
              caption="Usuários do cliente"
              columns={columns}
              rows={users}
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
            {!usersQuery.isPending && !hasPrimaryUser && (
              <Alert variant="info" title="Usuário principal">
                <p>
                  O usuário principal é criado automaticamente quando o cliente possui e-mail e
                  CPF/CNPJ válidos. Como ele ainda não existe, o formulário abaixo já está
                  preenchido com os dados do cliente — basta definir a senha e salvar.
                </p>
              </Alert>
            )}
          </Can>

          <Can permission={Permission.CLIENT_UPDATE}>
            <Section title="Novo usuário" description="Crie um acesso para este cliente.">
              <Form form={form} onSubmit={handleCreate} className="space-y-6">
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField name="name" label="Nome" required className="sm:col-span-2" />
                  <TextField name="email" label="E-mail" type="email" required />
                  <TextField name="phone" label="Telefone" />
                  <TextField
                    name="password"
                    label="Senha"
                    type="password"
                    autoComplete="new-password"
                    hint="Em branco, usa os 6 primeiros dígitos do CPF do cliente."
                  />
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

      <ClientUserEditModal
        open={userToEdit !== null}
        user={userToEdit}
        submitting={updateUser.isPending}
        onClose={() => setUserToEdit(null)}
        onSubmit={async (payload) => {
          if (!userToEdit) return
          await updateUser.mutateAsync({ userId: userToEdit.id, payload })
          setUserToEdit(null)
        }}
      />
    </>
  )
}
