import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { CircleDollarSign } from 'lucide-react'
import {
  Badge,
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
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { useTableSort } from '@/shared/hooks/useTableSort'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Commission } from '@/shared/types/models'
import { useCommissionsQuery } from '../hooks/useCommissions'
import {
  COMMISSION_STATUS_OPTIONS,
  commissionStatusBadgeVariant,
  commissionStatusLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function CommissionsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''

  const { can } = usePermissions()
  const canViewOthers = can(Permission.COMMISSION_VIEW_OTHERS)
  const canViewServiceOrders = can(Permission.SERVICE_ORDER_READ)
  const { sort, sortParam, toggleSort } = useTableSort()

  const query = useCommissionsQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status || undefined,
    sort: sortParam,
  })

  const setFilter = (key: string, value: string) => {
    setSearchParams(
      (params) => {
        value ? params.set(key, value) : params.delete(key)
        params.delete('page')
        return params
      },
      { replace: true },
    )
  }

  const columns: Array<Column<Commission>> = [
    {
      key: 'code',
      header: 'OS',
      render: (commission) => {
        const order = commission.service_order
        if (!order) return <span className="text-muted">—</span>

        return canViewServiceOrders ? (
          <Link
            to={`/service-orders/${order.id}`}
            className="font-medium text-primary hover:underline"
          >
            {order.code}
          </Link>
        ) : (
          <span className="font-medium text-foreground">{order.code}</span>
        )
      },
    },
    {
      key: 'client',
      header: 'Cliente',
      render: (commission) => commission.service_order?.client?.name ?? '—',
    },
    {
      key: 'technician',
      header: 'Técnico',
      render: (commission) => commission.technician?.name ?? 'Sem técnico',
    },
    {
      key: 'base_amount',
      header: 'Valor dos serviços',
      render: (commission) => (
        <span className="text-muted">{formatCurrency(commission.base_amount)}</span>
      ),
    },
    {
      key: 'amount_cents',
      header: 'Comissão',
      sortable: true,
      sortKey: 'amount_cents',
      render: (commission) => (
        <span className="font-semibold text-foreground">{formatCurrency(commission.amount)}</span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      sortable: true,
      render: (commission) => (
        <Badge variant={commissionStatusBadgeVariant(commission.status)}>
          {commission.status_label ?? commissionStatusLabel(commission.status)}
        </Badge>
      ),
    },
    {
      key: 'finalized_at',
      header: 'Finalizada em',
      sortable: true,
      render: (commission) =>
        commission.finalized_at ? (
          formatDate(commission.finalized_at)
        ) : (
          <span className="text-muted">—</span>
        ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Comissões"
        description={
          canViewOthers
            ? 'Comissões previstas das OS abertas ou em andamento e finalizadas das OS concluídas.'
            : 'Você visualiza apenas as comissões atribuídas a você.'
        }
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Comissões' },
        ]}
      />

      <PageContent variant="table">
        <FilterBar>
          <SearchInput
            placeholder="Buscar por OS, cliente ou técnico..."
            aria-label="Buscar comissões"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setFilter('search', event.target.value)
            }}
          />
          <Select
            aria-label="Filtrar comissões por status"
            className="w-48"
            value={status}
            onChange={(event) => setFilter('status', event.target.value)}
            options={[{ value: '', label: 'Todas' }, ...COMMISSION_STATUS_OPTIONS]}
          />
        </FilterBar>

        <DataTable
          fillHeight
          caption="Lista de comissões"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(commission) => commission.id}
          loading={query.isPending}
          sort={sort}
          onSort={toggleSort}
          emptyState={
            <EmptyState
              icon={CircleDollarSign}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhuma comissão registrada'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'As comissões são criadas automaticamente quando uma OS é cadastrada.'
              }
            />
          }
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) =>
              setSearchParams(
                (params) => {
                  next > 1 ? params.set('page', String(next)) : params.delete('page')
                  return params
                },
                { replace: true },
              )
            }
          />
        )}
      </PageContent>
    </Page>
  )
}
