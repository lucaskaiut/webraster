import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, Trash2, Users } from 'lucide-react'
import {
  Avatar,
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useSessionStore } from '@/shared/stores/session.store'
import { formatDate } from '@/shared/utils/format'
import { formatDocument } from '@/shared/utils/document'
import type { User } from '@/shared/types/models'
import { useDeleteUser, useUsersQuery } from '../hooks/useUsers'

const PER_PAGE = 10

export default function UsersListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()
  const currentUser = useSessionStore((state) => state.user)

  const [userToDelete, setUserToDelete] = useState<User | null>(null)
  const deleteUser = useDeleteUser()

  const query = useUsersQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

  const updateParams = (next: { page?: number; search?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.page !== undefined) {
          next.page > 1 ? params.set('page', String(next.page)) : params.delete('page')
        }
        return params
      },
      { replace: true },
    )
  }

  const handleSearch = (value: string) => {
    setSearch(value)
    updateParams({ search: value })
  }

  const confirmDelete = () => {
    if (!userToDelete) return

    deleteUser.mutate(userToDelete.id, { onSettled: () => setUserToDelete(null) })
  }

  const canMutate = can(Permission.USER_UPDATE) || can(Permission.USER_DELETE)

  const columns: Array<Column<User>> = [
    {
      key: 'name',
      header: 'Usuário',
      render: (user) => (
        <div className="flex items-center gap-3">
          <Avatar name={user.name} />
          <div className="min-w-0">
            <p className="truncate font-medium text-foreground">{user.name}</p>
            <p className="truncate text-[13px] text-muted">{user.email}</p>
          </div>
        </div>
      ),
    },
    {
      key: 'document',
      header: 'CPF',
      render: (user) => <span className="text-muted">{formatDocument(user.document)}</span>,
    },
    {
      key: 'roles',
      header: 'Perfis',
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
      key: 'created_at',
      header: 'Criado em',
      render: (user) => <span className="text-muted">{formatDate(user.created_at)}</span>,
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (user: User) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.USER_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/users/${user.id}/edit`)}
                    aria-label={`Editar ${user.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.USER_DELETE) && user.id !== currentUser?.id && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setUserToDelete(user)}
                    aria-label={`Excluir ${user.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<User>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Usuários"
        description="Gerencie os usuários da sua organização."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Usuários' }]}
        actions={
          <Can permission={Permission.USER_CREATE}>
            <ButtonLink to="/users/create">
              <Plus className="size-4" />
              Novo usuário
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome ou e-mail..."
            aria-label="Buscar usuários"
            value={search}
            onChange={(event) => handleSearch(event.target.value)}
          />
        </FilterBar>

        <DataTable
          caption="Lista de usuários"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(user) => user.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Users}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum usuário cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro usuário da sua organização.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.USER_CREATE}>
                    <ButtonLink to="/users/create">
                      <Plus className="size-4" />
                      Novo usuário
                    </ButtonLink>
                  </Can>
                ) : undefined
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={userToDelete !== null}
        onClose={() => setUserToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteUser.isPending}
        title="Excluir usuário"
        description={
          <>
            Tem certeza que deseja excluir <strong>{userToDelete?.name}</strong>? Esta ação não pode
            ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
