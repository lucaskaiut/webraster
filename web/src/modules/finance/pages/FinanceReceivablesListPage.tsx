import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { Eye, Receipt } from 'lucide-react'
import {
  Badge,
  Button,
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
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { FinanceReceivable } from '@/shared/types/models'
import { useFinanceReceivablesQuery } from '../hooks/useFinance'
import {
  RECEIVABLE_STATUS_OPTIONS,
  paymentMethodLabel,
  receivableStatusBadgeVariant,
  receivableStatusLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function FinanceReceivablesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''
  const navigate = useNavigate()

  const query = useFinanceReceivablesQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status || undefined,
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

  const columns: Array<Column<FinanceReceivable>> = [
    {
      key: 'code',
      header: 'Código',
      render: (item) => (
        <Link
          to={`/finance/receivables/${item.id}`}
          className="font-medium text-primary hover:underline"
        >
          {item.code}
        </Link>
      ),
    },
    {
      key: 'client',
      header: 'Cliente',
      render: (item) => item.client?.name ?? '—',
    },
    {
      key: 'contract',
      header: 'Contrato',
      render: (item) => item.contract?.code ?? '—',
    },
    {
      key: 'total',
      header: 'Total',
      render: (item) => formatCurrency(item.total),
    },
    {
      key: 'due_at',
      header: 'Vencimento',
      render: (item) => formatDate(item.due_at),
    },
    {
      key: 'payment_method',
      header: 'Método',
      render: (item) =>
        item.payment_method_label ?? paymentMethodLabel(item.payment_method),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={receivableStatusBadgeVariant(item.status)}>
          {item.status_label ?? receivableStatusLabel(item.status)}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-16 text-right',
      render: (item) => (
        <div className="flex justify-end">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate(`/finance/receivables/${item.id}`)}
            aria-label={`Ver ${item.code}`}
          >
            <Eye className="size-4" />
          </Button>
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Cobranças"
        description="Contas a receber geradas pelos contratos."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Cobranças' },
        ]}
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar cobranças..."
            aria-label="Buscar cobranças"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setFilter('search', event.target.value)
            }}
          />
          <Select
            aria-label="Status"
            className="w-52"
            value={status}
            onChange={(event) => setFilter('status', event.target.value)}
            options={[{ value: '', label: 'Todos os status' }, ...RECEIVABLE_STATUS_OPTIONS]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de cobranças"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Receipt}
              title="Nenhuma cobrança"
              description="Gere cobranças a partir de um contrato ativo."
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
