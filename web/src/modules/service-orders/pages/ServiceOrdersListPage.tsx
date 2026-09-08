import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { ClipboardList, Columns3, CalendarDays, Eye, Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { ServiceOrder } from '@/shared/types/models'
import { formatDateTime } from '@/shared/utils/format'
import { useDeleteServiceOrder, useServiceOrdersQuery } from '../hooks/useServiceOrders'
import {
  priorityBadgeVariant,
  priorityLabel,
  SERVICE_ORDER_PRIORITY_OPTIONS,
  SERVICE_ORDER_STATUS_OPTIONS,
  SERVICE_ORDER_TYPE_OPTIONS,
  statusBadgeVariant,
  statusLabel,
  typeLabel,
} from '../lib/labels'

const PER_PAGE = 10

export default function ServiceOrdersListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''
  const type = searchParams.get('type') ?? ''
  const priority = searchParams.get('priority') ?? ''
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [toDelete, setToDelete] = useState<ServiceOrder | null>(null)
  const remove = useDeleteServiceOrder()

  const query = useServiceOrdersQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status || undefined,
    type: type || undefined,
    priority: priority || undefined,
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

  const columns: Array<Column<ServiceOrder>> = [
    {
      key: 'code',
      header: 'Número',
      render: (item) => (
        <Link to={`/service-orders/${item.id}`} className="font-medium text-primary hover:underline">
          {item.code}
        </Link>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (item) => <span className="text-muted">{item.type_label ?? typeLabel(item.type)}</span>,
    },
    {
      key: 'client',
      header: 'Cliente',
      render: (item) => <span className="text-foreground">{item.client?.name ?? '—'}</span>,
    },
    {
      key: 'vehicle',
      header: 'Veículo',
      render: (item) => <span className="text-muted">{item.vehicle?.plate ?? '—'}</span>,
    },
    {
      key: 'technician',
      header: 'Técnico',
      render: (item) => <span className="text-muted">{item.technician?.name ?? '—'}</span>,
    },
    {
      key: 'schedule',
      header: 'Agendamento',
      render: (item) => (
        <span className="text-muted">
          {item.scheduled_start_at ? formatDateTime(item.scheduled_start_at) : '—'}
        </span>
      ),
    },
    {
      key: 'priority',
      header: 'Prioridade',
      render: (item) => (
        <Badge variant={priorityBadgeVariant(item.priority)}>
          {item.priority_label ?? priorityLabel(item.priority)}
        </Badge>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={statusBadgeVariant(item.status)}>
          {item.status_label ?? statusLabel(item.status)}
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
            onClick={() => navigate(`/service-orders/${item.id}`)}
            aria-label={`Ver ${item.code}`}
          >
            <Eye className="size-4" />
          </Button>
          {can(Permission.SERVICE_ORDER_UPDATE) && item.status !== 'completed' && item.status !== 'cancelled' && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(`/service-orders/${item.id}/edit`)}
              aria-label={`Editar ${item.code}`}
            >
              <Pencil className="size-4" />
            </Button>
          )}
          {can(Permission.SERVICE_ORDER_DELETE) && item.status !== 'in_progress' && (
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
        title="Ordens de serviço"
        description="Controle operacional de instalações, manutenções e retiradas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Ordens de serviço' }]}
        actions={
          <div className="flex flex-wrap gap-2">
            <ButtonLink to="/service-orders/kanban" variant="secondary">
              <Columns3 className="size-4" />
              Kanban
            </ButtonLink>
            <ButtonLink to="/service-orders/calendar" variant="secondary">
              <CalendarDays className="size-4" />
              Agenda
            </ButtonLink>
            <Can permission={Permission.SERVICE_ORDER_CREATE}>
              <ButtonLink to="/service-orders/create">
                <Plus className="size-4" />
                Nova OS
              </ButtonLink>
            </Can>
          </div>
        }
      />
      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por número ou descrição..."
            aria-label="Buscar OS"
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
            options={[{ value: '', label: 'Todos os status' }, ...SERVICE_ORDER_STATUS_OPTIONS]}
          />
          <Select
            aria-label="Tipo"
            className="w-44"
            value={type}
            onChange={(event) => setFilter('type', event.target.value)}
            options={[{ value: '', label: 'Todos os tipos' }, ...SERVICE_ORDER_TYPE_OPTIONS]}
          />
          <Select
            aria-label="Prioridade"
            className="w-44"
            value={priority}
            onChange={(event) => setFilter('priority', event.target.value)}
            options={[{ value: '', label: 'Todas as prioridades' }, ...SERVICE_ORDER_PRIORITY_OPTIONS]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de ordens de serviço"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={ClipboardList}
              title="Nenhuma ordem de serviço"
              description="Crie a primeira OS para começar o fluxo operacional."
              action={
                <Can permission={Permission.SERVICE_ORDER_CREATE}>
                  <ButtonLink to="/service-orders/create">Nova OS</ButtonLink>
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
        title="Excluir OS"
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
