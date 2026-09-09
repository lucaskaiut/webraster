import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { RefreshCw, Repeat, XCircle } from 'lucide-react'
import {
  Badge,
  Button,
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
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatDate } from '@/shared/utils/format'
import type { FinanceSubscription } from '@/shared/types/models'
import {
  useCancelFinanceSubscription,
  useFinanceSubscriptionsQuery,
  useReactivateFinanceSubscription,
} from '../hooks/useFinance'
import {
  SUBSCRIPTION_STATUS_OPTIONS,
  periodicityLabel,
  subscriptionStatusBadgeVariant,
  subscriptionStatusLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function FinanceSubscriptionsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''
  const { can } = usePermissions()
  const [toCancel, setToCancel] = useState<FinanceSubscription | null>(null)
  const cancel = useCancelFinanceSubscription()
  const reactivate = useReactivateFinanceSubscription()

  const query = useFinanceSubscriptionsQuery({
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

  const columns: Array<Column<FinanceSubscription>> = [
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
      key: 'plan',
      header: 'Plano',
      render: (item) => item.contract?.plan?.name ?? '—',
    },
    {
      key: 'periodicity',
      header: 'Periodicidade',
      render: (item) => item.periodicity_label ?? periodicityLabel(item.periodicity),
    },
    {
      key: 'next_billing_at',
      header: 'Próxima cobrança',
      render: (item) => formatDate(item.next_billing_at),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={subscriptionStatusBadgeVariant(item.status)}>
          {item.status_label ?? subscriptionStatusLabel(item.status)}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-28 text-right',
      render: (item) => (
        <div className="flex justify-end gap-1">
          {can(Permission.FINANCE_SUBSCRIPTION_UPDATE) && item.status === 'active' && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setToCancel(item)}
              aria-label="Cancelar assinatura"
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <XCircle className="size-4" />
            </Button>
          )}
          {can(Permission.FINANCE_SUBSCRIPTION_UPDATE) &&
            (item.status === 'cancelled' || item.status === 'suspended') && (
              <Button
                variant="ghost"
                size="sm"
                loading={reactivate.isPending}
                onClick={() => reactivate.mutate(item.id)}
                aria-label="Reativar assinatura"
              >
                <RefreshCw className="size-4" />
              </Button>
            )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Assinaturas"
        description="Assinaturas recorrentes geradas a partir dos contratos."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Assinaturas' },
        ]}
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar assinaturas..."
            aria-label="Buscar assinaturas"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setFilter('search', event.target.value)
            }}
          />
          <Select
            aria-label="Status"
            className="w-44"
            value={status}
            onChange={(event) => setFilter('status', event.target.value)}
            options={[{ value: '', label: 'Todos os status' }, ...SUBSCRIPTION_STATUS_OPTIONS]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de assinaturas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Repeat}
              title="Nenhuma assinatura"
              description="Assinaturas aparecem ao criar contratos ativos."
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

      <ConfirmDialog
        open={toCancel !== null}
        onClose={() => setToCancel(null)}
        onConfirm={() => {
          if (!toCancel) return
          cancel.mutate(toCancel.id, { onSettled: () => setToCancel(null) })
        }}
        loading={cancel.isPending}
        title="Cancelar assinatura"
        description="Tem certeza que deseja cancelar esta assinatura?"
        confirmLabel="Cancelar assinatura"
      />
    </Page>
  )
}
