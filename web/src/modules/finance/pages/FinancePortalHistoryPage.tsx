import { Link, useNavigate, useSearchParams } from 'react-router'
import { Eye, History } from 'lucide-react'
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
  Select,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { FinanceReceivable } from '@/shared/types/models'
import { useFinancePortalReceivablesQuery } from '../hooks/useFinance'
import {
  paymentMethodLabel,
  receivableStatusBadgeVariant,
  receivableStatusLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function FinancePortalHistoryPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? 'received'
  const navigate = useNavigate()

  const query = useFinancePortalReceivablesQuery({
    page,
    per_page: PER_PAGE,
    status: status || undefined,
  })

  const columns: Array<Column<FinanceReceivable>> = [
    {
      key: 'code',
      header: 'Fatura',
      render: (item) => (
        <Link
          to={`/finance/portal/receivables/${item.id}`}
          className="font-medium text-primary hover:underline"
        >
          {item.code}
        </Link>
      ),
    },
    {
      key: 'total',
      header: 'Valor',
      render: (item) => formatCurrency(item.total),
    },
    {
      key: 'due_at',
      header: 'Vencimento',
      render: (item) => formatDate(item.due_at),
    },
    {
      key: 'paid_at',
      header: 'Pago / cancelado',
      render: (item) => formatDate(item.paid_at ?? item.cancelled_at),
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
            onClick={() => navigate(`/finance/portal/receivables/${item.id}`)}
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
        title="Histórico financeiro"
        description="Faturas recebidas ou canceladas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Histórico' }]}
      />
      <PageContent>
        <FilterBar>
          <Select
            aria-label="Status"
            className="w-52"
            value={status}
            onChange={(event) => {
              setSearchParams(
                (params) => {
                  params.set('status', event.target.value)
                  params.delete('page')
                  return params
                },
                { replace: true },
              )
            }}
            options={[
              { value: 'received', label: 'Recebidas' },
              { value: 'cancelled', label: 'Canceladas' },
              { value: 'refunded', label: 'Estornadas' },
            ]}
          />
        </FilterBar>

        <DataTable
          caption="Histórico de faturas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={History}
              title="Nenhum registro"
              description="O histórico de pagamentos aparecerá aqui."
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
