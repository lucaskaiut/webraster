import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, Trash2, Warehouse } from 'lucide-react'
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
import { useTableSort } from '@/shared/hooks/useTableSort'
import type { Stock } from '@/shared/types/models'
import { useDeleteStock, useStocksQuery } from '../hooks/useStocks'

const PER_PAGE = 10

export default function StocksListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const statusParam = searchParams.get('is_active')

  const navigate = useNavigate()
  const { can } = usePermissions()
  const { sort, sortParam, toggleSort } = useTableSort()

  const [stockToDelete, setStockToDelete] = useState<Stock | null>(null)
  const deleteStock = useDeleteStock()

  const query = useStocksQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    is_active: statusParam === null ? undefined : statusParam === '1',
    sort: sortParam,
  })

  const updateParams = (next: { page?: number; search?: string; status?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          if (next.search) {
            params.set('search', next.search)
          } else {
            params.delete('search')
          }
          params.delete('page')
        }
        if (next.status !== undefined) {
          if (next.status) {
            params.set('is_active', next.status)
          } else {
            params.delete('is_active')
          }
          params.delete('page')
        }
        if (next.page !== undefined) {
          if (next.page > 1) {
            params.set('page', String(next.page))
          } else {
            params.delete('page')
          }
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
    if (!stockToDelete) return

    deleteStock.mutate(stockToDelete.id, {
      onSettled: () => setStockToDelete(null),
    })
  }

  const canMutate = can(Permission.STOCK_UPDATE) || can(Permission.STOCK_DELETE)

  const columns: Array<Column<Stock>> = [
    {
      key: 'name',
      header: 'Estoque',
      sortable: true,
      render: (stock) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{stock.name}</p>
          <p className="truncate text-[13px] text-muted">{stock.description ?? 'Sem descrição'}</p>
        </div>
      ),
    },
    {
      key: 'equipments',
      header: 'Equipamentos no estoque',
      render: (stock) => (
        <span className="text-muted">
          {stock.equipments_count ?? 0} equipamento{(stock.equipments_count ?? 0) === 1 ? '' : 's'}
        </span>
      ),
    },
    {
      key: 'technicians',
      header: 'Técnicos',
      render: (stock) => (
        <span className="text-muted">
          {stock.technicians_count ?? 0} técnico{(stock.technicians_count ?? 0) === 1 ? '' : 's'}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'Status',
      sortable: true,
      render: (stock) => (
        <Badge variant={stock.is_active ? 'success' : 'neutral'}>
          {stock.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (stock: Stock) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.STOCK_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/stocks/${stock.id}/edit`)}
                    aria-label={`Editar ${stock.name}`}>
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.STOCK_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setStockToDelete(stock)}
                    aria-label={`Excluir ${stock.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger">
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Stock>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Estoques"
        description="Organize os equipamentos por estoque e vincule cada técnico ao estoque que ele utiliza."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Estoques' }]}
        actions={
          <Can permission={Permission.STOCK_CREATE}>
            <ButtonLink to="/stocks/create">
              <Plus className="size-4" />
              Novo estoque
            </ButtonLink>
          </Can>
        }
      />

      <PageContent variant="table">
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome ou descrição..."
            aria-label="Buscar estoques"
            value={search}
            onChange={(event) => handleSearch(event.target.value)}
          />
          <Select
            aria-label="Filtrar estoques por status"
            className="w-40"
            value={statusParam ?? ''}
            onChange={(event) => updateParams({ status: event.target.value })}
            options={[
              { value: '', label: 'Todos os status' },
              { value: '1', label: 'Ativos' },
              { value: '0', label: 'Inativos' },
            ]}
          />
        </FilterBar>

        <DataTable
          fillHeight
          caption="Lista de estoques"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(stock) => stock.id}
          loading={query.isPending}
          sort={sort}
          onSort={toggleSort}
          emptyState={
            <EmptyState
              icon={Warehouse}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum estoque cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro estoque da operação.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.STOCK_CREATE}>
                    <ButtonLink to="/stocks/create">
                      <Plus className="size-4" />
                      Novo estoque
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
        open={stockToDelete !== null}
        onClose={() => setStockToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteStock.isPending}
        title="Excluir estoque"
        description={
          <>
            Tem certeza que deseja excluir <strong>{stockToDelete?.name}</strong>? Só é possível
            excluir estoques sem equipamentos e sem técnicos vinculados.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
