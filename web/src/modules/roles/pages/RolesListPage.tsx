import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react'
import {
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import type { Role } from '@/shared/types/models'
import { useDeleteRole, useRolesQuery } from '../hooks/useRoles'

export default function RolesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [roleToDelete, setRoleToDelete] = useState<Role | null>(null)
  const deleteRole = useDeleteRole()

  const query = useRolesQuery({ page, per_page: 10 })

  const confirmDelete = () => {
    if (!roleToDelete) return

    deleteRole.mutate(roleToDelete.id, { onSettled: () => setRoleToDelete(null) })
  }

  const canMutate = can(Permission.ROLE_UPDATE) || can(Permission.ROLE_DELETE)

  const columns: Array<Column<Role>> = [
    {
      key: 'name',
      header: 'Perfil',
      render: (role) => (
        <div className="min-w-0">
          <p className="font-medium text-foreground">{role.name}</p>
          {role.description && <p className="truncate text-[13px] text-muted">{role.description}</p>}
        </div>
      ),
    },
    {
      key: 'permissions',
      header: 'Permissões',
      render: (role) => (
        <span className="text-muted">
          {role.permissions?.length ?? 0} {role.permissions?.length === 1 ? 'permissão' : 'permissões'}
        </span>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (role: Role) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.ROLE_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/roles/${role.id}/edit`)}
                    aria-label={`Editar ${role.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.ROLE_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setRoleToDelete(role)}
                    aria-label={`Excluir ${role.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Role>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Perfis de acesso"
        description="Defina o que cada grupo de usuários pode fazer."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Perfis de acesso' }]}
        actions={
          <Can permission={Permission.ROLE_CREATE}>
            <ButtonLink to="/roles/create">
              <Plus className="size-4" />
              Novo perfil
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <DataTable
          caption="Lista de perfis de acesso"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(role) => role.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={ShieldCheck}
              title="Nenhum perfil cadastrado"
              description="Crie perfis para controlar o acesso dos usuários."
            />
          }
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) =>
              setSearchParams((params) => {
                next > 1 ? params.set('page', String(next)) : params.delete('page')
                return params
              })
            }
          />
        )}
      </PageContent>

      <ConfirmDialog
        open={roleToDelete !== null}
        onClose={() => setRoleToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteRole.isPending}
        title="Excluir perfil"
        description={
          <>
            Tem certeza que deseja excluir o perfil <strong>{roleToDelete?.name}</strong>? Usuários
            vinculados perderão as permissões deste perfil.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
