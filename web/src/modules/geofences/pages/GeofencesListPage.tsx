import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { MapPinned, Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { Geofence } from '@/shared/types/models'
import { useDeleteGeofence, useGeofencesQuery } from '../hooks/useGeofences'

const PER_PAGE = 10

export default function GeofencesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<Geofence | null>(null)
  const deleteGeofence = useDeleteGeofence()

  const query = useGeofencesQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
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

  const canMutate = can(Permission.GEOFENCE_UPDATE) || can(Permission.GEOFENCE_DELETE)

  const columns: Array<Column<Geofence>> = [
    {
      key: 'name',
      header: 'Geocerca',
      render: (item) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{item.name}</p>
          <p className="truncate text-[13px] text-muted">{item.client?.name ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (item) => (
        <Badge variant={item.type === 'circle' ? 'success' : 'primary'}>
          {item.type === 'circle' ? 'Círculo' : 'Polígono'}
        </Badge>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={item.is_active ? 'success' : 'neutral'}>
          {item.is_active ? 'Ativa' : 'Inativa'}
        </Badge>
      ),
    },
    {
      key: 'events',
      header: 'Eventos',
      render: (item) => <span className="text-muted">{item.events_count ?? 0}</span>,
    },
    {
      key: 'created',
      header: 'Criada em',
      render: (item) => (
        <span className="text-muted">
          {item.created_at ? new Date(item.created_at).toLocaleDateString('pt-BR') : '—'}
        </span>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (item: Geofence) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.GEOFENCE_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/geofences/${item.id}/edit`)}
                    aria-label={`Editar ${item.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.GEOFENCE_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setToDelete(item)}
                    aria-label={`Excluir ${item.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Geofence>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Geocercas"
        description="Cercas virtuais circulares e poligonais."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Geocercas' }]}
        actions={
          <Can permission={Permission.GEOFENCE_CREATE}>
            <ButtonLink to="/geofences/create">
              <Plus className="size-4" />
              Nova geocerca
            </ButtonLink>
          </Can>
        }
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar geocerca..."
            aria-label="Buscar geocercas"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de geocercas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={MapPinned}
              title="Nenhuma geocerca"
              description="Crie a primeira cerca virtual para começar a gerar eventos."
              action={
                <Can permission={Permission.GEOFENCE_CREATE}>
                  <ButtonLink to="/geofences/create">Nova geocerca</ButtonLink>
                </Can>
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => {
          if (!toDelete) return
          deleteGeofence.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
        }}
        loading={deleteGeofence.isPending}
        title="Excluir geocerca"
        description={
          <>
            Remover <strong>{toDelete?.name}</strong>? O histórico de eventos permanece associado
            (exclusão lógica).
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
