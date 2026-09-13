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
  Select,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { FinanceBilling } from '@/shared/types/models'
import { useFinancePortalBillingsQuery } from '../hooks/useFinance'
import {
  BILLING_STATUS_OPTIONS,
  billingStatusBadgeVariant,
  billingStatusLabel,
  paymentMethodLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function FinancePortalBillingsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? 'open'
  const navigate = useNavigate()

  const query = useFinancePortalBillingsQuery({
    page,
    per_page: PER_PAGE,
    status: status === 'open' ? undefined : status || undefined,
  })

  const rows = (query.data?.data ?? []).filter((item) => {
    if (status !== 'open') return true
    return ['pending', 'awaiting_payment', 'overdue'].includes(item.status)
  })

  const columns: Array<Column<FinanceBilling>> = [
    {
      key: 'code',
      header: 'Fatura',
      render: (item) => (
        <Link
          to={`/finance/portal/billings/${item.id}`}
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
      key: 'payment_method',
      header: 'Método',
      render: (item) => item.payment_method_label ?? paymentMethodLabel(item.payment_method),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={billingStatusBadgeVariant(item.status)}>
          {item.status_label ?? billingStatusLabel(item.status)}
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
            onClick={() => navigate(`/finance/portal/billings/${item.id}`)}
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
        title="Minhas faturas"
        description="Cobranças em aberto e aguardando pagamento."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Minhas faturas' }]}
      />
      <PageContent>
        <FilterBar>
          <Select
            aria-label="Status"
            className="w-56"
            value={status}
            onChange={(event) => {
              setSearchParams(
                (params) => {
                  event.target.value
                    ? params.set('status', event.target.value)
                    : params.delete('status')
                  params.delete('page')
                  return params
                },
                { replace: true },
              )
            }}
            options={[
              { value: 'open', label: 'Em aberto' },
              ...BILLING_STATUS_OPTIONS.filter((o) =>
                ['pending', 'awaiting_payment', 'overdue'].includes(o.value),
              ),
            ]}
          />
        </FilterBar>

        <DataTable
          caption="Minhas faturas"
          columns={columns}
          rows={rows}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Receipt}
              title="Nenhuma fatura em aberto"
              description="Quando houver cobranças, elas aparecerão aqui."
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
