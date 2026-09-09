import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { Package, Pencil, Plus, Trash2 } from 'lucide-react'
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
import { formatCurrency } from '@/shared/utils/format'
import type { FinancePlan } from '@/shared/types/models'
import { useDeleteFinancePlan, useFinancePlansQuery } from '../hooks/useFinance'
import { periodicityLabel } from '../lib/labels'

const PER_PAGE = 10

export default function FinancePlansListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const isActive = searchParams.get('is_active') ?? ''
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<FinancePlan | null>(null)
  const remove = useDeleteFinancePlan()

  const query = useFinancePlansQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    is_active: isActive || undefined,
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

  const columns: Array<Column<FinancePlan>> = [
    {
      key: 'name',
      header: 'Nome',
      render: (item) => (
        <Link to={`/finance/plans/${item.id}/edit`} className="font-medium text-primary hover:underline">
          {item.name}
        </Link>
      ),
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
      key: 'device_limit',
      header: 'Dispositivos',
      render: (item) => item.device_limit ?? '—',
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
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-28 text-right',
      render: (item) => (
        <div className="flex justify-end gap-1">
          {can(Permission.FINANCE_PLAN_UPDATE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(`/finance/plans/${item.id}/edit`)}
              aria-label={`Editar ${item.name}`}
            >
              <Pencil className="size-4" />
            </Button>
          )}
          {can(Permission.FINANCE_PLAN_DELETE) && (
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
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Planos financeiros"
        description="Planos de cobrança recorrente para contratos de clientes."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Financeiro' },
          { label: 'Planos' },
        ]}
        actions={
          <Can permission={Permission.FINANCE_PLAN_CREATE}>
            <ButtonLink to="/finance/plans/create">
              <Plus className="size-4" />
              Novo plano
            </ButtonLink>
          </Can>
        }
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar planos..."
            aria-label="Buscar planos"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setFilter('search', event.target.value)
            }}
          />
          <Select
            aria-label="Status"
            className="w-44"
            value={isActive}
            onChange={(event) => setFilter('is_active', event.target.value)}
            options={[
              { value: '', label: 'Todos' },
              { value: '1', label: 'Ativos' },
              { value: '0', label: 'Inativos' },
            ]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de planos financeiros"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Package}
              title="Nenhum plano"
              description="Crie o primeiro plano financeiro."
              action={
                <Can permission={Permission.FINANCE_PLAN_CREATE}>
                  <ButtonLink to="/finance/plans/create">Novo plano</ButtonLink>
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
        title="Excluir plano"
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
