import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Contact, Plus, Trash2 } from 'lucide-react'
import {
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
import { useClientUsersQuery, useCreateClientUser, useDeleteClientUser } from '../hooks/useClients'
import { createClientUserSchema, type ClientUserFormValues } from '../schemas/client.schema'

export function ClientUsersSection({ clientId }: { clientId: string }) {
  const [userToDelete, setUserToDelete] = useState<User | null>(null)
  const usersQuery = useClientUsersQuery(clientId, { per_page: 50 })
  const createUser = useCreateClientUser(clientId)
  const deleteUser = useDeleteClientUser(clientId)

  const form = useForm<ClientUserFormValues>({
    resolver: zodResolver(createClientUserSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      role_ids: [],
    },
  })

  const handleCreate = async (values: ClientUserFormValues) => {
    try {
      await createUser.mutateAsync({
        name: values.name,
        email: values.email,
        password: values.password,
        ...(values.role_ids.length > 0 ? { role_ids: values.role_ids } : {}),
      })
      form.reset({
        name: '',
        email: '',
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
            title="Usuários vinculados"
            description="Contas de acesso deste cliente. Se nenhum perfil for selecionado, a API atribui o perfil Cliente."
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
    </>
  )
}
