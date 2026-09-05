import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react'
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
  Select,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import type { Poi } from '@/shared/types/models'
import { useDeletePoi, usePoiCategoriesQuery, usePoisQuery } from '../hooks/usePois'

const PER_PAGE = 10

export default function PoisListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const categoryId = searchParams.get('category_id') ?? ''
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<Poi | null>(null)
  const deletePoi = useDeletePoi()
  const categoriesQuery = usePoiCategoriesQuery()

  const query = usePoisQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    category_id: categoryId || undefined,
  })

  const updateParams = (next: { page?: number; search?: string; category_id?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.category_id !== undefined) {
          next.category_id ? params.set('category_id', next.category_id) : params.delete('category_id')
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

  const canMutate = can(Permission.POI_UPDATE) || can(Permission.POI_DELETE)

  const columns: Array<Column<Poi>> = [
    {
      key: 'name',
      header: 'POI',
      render: (item) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{item.name}</p>
          <p className="truncate text-[13px] text-muted">{item.client?.name ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'Categoria',
      render: (item) => <span className="text-muted">{item.category?.name ?? '—'}</span>,
    },
    {
      key: 'coords',
      header: 'Coordenadas',
      render: (item) => (
        <span className="text-[13px] text-muted">
          {item.latitude.toFixed(5)}, {item.longitude.toFixed(5)}
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={item.is_active ? 'success' : 'neutral'}>
          {item.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (item: Poi) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.POI_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/pois/${item.id}/edit`)}
                    aria-label={`Editar ${item.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.POI_DELETE) && (
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
          } satisfies Column<Poi>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Pontos de interesse"
        description="Locais relevantes associados a clientes e categorias."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'POIs' }]}
        actions={
          <Can permission={Permission.POI_CREATE}>
            <ButtonLink to="/pois/create">
              <Plus className="size-4" />
              Novo POI
            </ButtonLink>
          </Can>
        }
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar POI..."
            aria-label="Buscar POIs"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
          <Select
            aria-label="Filtrar por categoria"
            className="w-52"
            value={categoryId}
            onChange={(event) => updateParams({ category_id: event.target.value })}
            options={[
              { value: '', label: 'Todas as categorias' },
              ...(categoriesQuery.data ?? []).map((category) => ({
                value: category.id,
                label: category.name,
              })),
            ]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de POIs"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={MapPin}
              title="Nenhum POI"
              description="Cadastre oficinas, postos, bases e outros pontos no mapa."
              action={
                <Can permission={Permission.POI_CREATE}>
                  <ButtonLink to="/pois/create">Novo POI</ButtonLink>
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
          deletePoi.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
        }}
        loading={deletePoi.isPending}
        title="Excluir POI"
        description={
          <>
            Remover <strong>{toDelete?.name}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
