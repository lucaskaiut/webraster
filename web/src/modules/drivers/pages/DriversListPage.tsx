import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { IdCard, Pencil, Plus, Trash2 } from 'lucide-react'
import {
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
import { formatDate } from '@/shared/utils/format'
import { formatDocument } from '@/shared/utils/document'
import type { Driver } from '@/shared/types/models'
import { useDeleteDriver, useDriversQuery } from '../hooks/useDrivers'

const PER_PAGE = 10

export default function DriversListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const clientId = searchParams.get('client_id') ?? undefined

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [driverToDelete, setDriverToDelete] = useState<Driver | null>(null)
  const deleteDriver = useDeleteDriver()

  const query = useDriversQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    client_id: clientId,
  })

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
    if (!driverToDelete) return

    deleteDriver.mutate(driverToDelete.id, { onSettled: () => setDriverToDelete(null) })
  }

  const canMutate = can(Permission.DRIVER_UPDATE) || can(Permission.DRIVER_DELETE)

  const columns: Array<Column<Driver>> = [
    {
      key: 'name',
      header: 'Motorista',
      render: (driver) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{driver.name}</p>
          <p className="truncate text-[13px] text-muted">{driver.email ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'client',
      header: 'Cliente',
      render: (driver) => (
        <span className="text-muted">{driver.client?.name ?? '—'}</span>
      ),
    },
    {
      key: 'document',
      header: 'CPF',
      render: (driver) => <span className="text-muted">{formatDocument(driver.document)}</span>,
    },
    {
      key: 'cnh_expires_at',
      header: 'Validade CNH',
      render: (driver) => <span className="text-muted">{formatDate(driver.cnh_expires_at)}</span>,
    },
    {
      key: 'is_active',
      header: 'Status',
      render: (driver) => (
        <Badge variant={driver.is_active ? 'success' : 'neutral'}>
          {driver.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (driver: Driver) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.DRIVER_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/drivers/${driver.id}/edit`)}
                    aria-label={`Editar ${driver.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.DRIVER_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setDriverToDelete(driver)}
                    aria-label={`Excluir ${driver.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Driver>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Motoristas"
        description="Gerencie os motoristas vinculados aos clientes."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Motoristas' }]}
        actions={
          <Can permission={Permission.DRIVER_CREATE}>
            <ButtonLink to="/drivers/create">
              <Plus className="size-4" />
              Novo motorista
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome, CPF ou e-mail..."
            aria-label="Buscar motoristas"
            value={search}
            onChange={(event) => handleSearch(event.target.value)}
          />
        </FilterBar>

        <DataTable
          caption="Lista de motoristas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(driver) => driver.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={IdCard}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum motorista cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro motorista.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.DRIVER_CREATE}>
                    <ButtonLink to="/drivers/create">
                      <Plus className="size-4" />
                      Novo motorista
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
        open={driverToDelete !== null}
        onClose={() => setDriverToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteDriver.isPending}
        title="Excluir motorista"
        description={
          <>
            Tem certeza que deseja excluir <strong>{driverToDelete?.name}</strong>? Esta ação não
            pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
