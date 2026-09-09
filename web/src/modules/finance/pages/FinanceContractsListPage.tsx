import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { Eye, FileText, Plus, Trash2 } from 'lucide-react'
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
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { FinanceContract } from '@/shared/types/models'
import { useDeleteFinanceContract, useFinanceContractsQuery } from '../hooks/useFinance'
import {
  CONTRACT_STATUS_OPTIONS,
  contractStatusBadgeVariant,
  contractStatusLabel,
  periodicityLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function FinanceContractsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<FinanceContract | null>(null)
  const remove = useDeleteFinanceContract()

  const query = useFinanceContractsQuery({
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

  const columns: Array<Column<FinanceContract>> = [
    {
      key: 'code',
      header: 'Código',
      render: (item) => (
        <Link to={`/finance/contracts/${item.id}`} className="font-medium text-primary hover:underline">
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
      key: 'plan',
      header: 'Plano',
      render: (item) => item.plan?.name ?? '—',
    },
    {
      key: 'amount',
      header: 'Valor',
      render: (item) => formatCurrency(item.amount),
    },
    {
      key: 'periodicity',
      header: 'Periodicidade',
      render: (item) => item.periodicity_label ?? periodicityLabel(item.periodicity),
    },
    {
      key: 'starts_at',
      header: 'Início',
      render: (item) => formatDate(item.starts_at),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={contractStatusBadgeVariant(item.status)}>
          {item.status_label ?? contractStatusLabel(item.status)}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-28 text-right',
      render: (item) => (
        <div className="flex justify-end gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => navigate(`/finance/contracts/${item.id}`)}
            aria-label={`Ver ${item.code}`}
          >
            <Eye className="size-4" />
          </Button>
          {can(Permission.FINANCE_CONTRACT_DELETE) && item.status !== 'cancelled' && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setToDelete(item)}
              aria-label={`Excluir ${item.code}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Contratos"
        description="Contratos financeiros vinculados a clientes e planos."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Contratos' },
        ]}
        actions={
          <Can permission={Permission.FINANCE_CONTRACT_CREATE}>
            <ButtonLink to="/finance/contracts/create">
              <Plus className="size-4" />
              Novo contrato
            </ButtonLink>
          </Can>
        }
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar contratos..."
            aria-label="Buscar contratos"
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
            options={[{ value: '', label: 'Todos os status' }, ...CONTRACT_STATUS_OPTIONS]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de contratos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={FileText}
              title="Nenhum contrato"
              description="Crie o primeiro contrato financeiro."
              action={
                <Can permission={Permission.FINANCE_CONTRACT_CREATE}>
                  <ButtonLink to="/finance/contracts/create">Novo contrato</ButtonLink>
                </Can>
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

      <ConfirmDialog
        open={toDelete !== null}
        onClose={() => setToDelete(null)}
        onConfirm={() => {
          if (!toDelete) return
          remove.mutate(toDelete.id, { onSettled: () => setToDelete(null) })
        }}
        loading={remove.isPending}
        title="Excluir contrato"
        description={
          <>
            Remover <strong>{toDelete?.code}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
