import { useSearchParams } from 'react-router'
import { BellRing } from 'lucide-react'
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
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import type { Alert } from '@/shared/types/models'
import { useAcknowledgeAlert, useAlertsQuery, useResolveAlert } from '../hooks/useAlerts'

const PER_PAGE = 15

const TYPE_OPTIONS = [
  { value: '', label: 'Todos os tipos' },
  { value: 'speed', label: 'Velocidade' },
  { value: 'ignition_on', label: 'Ignição ligada' },
  { value: 'ignition_off', label: 'Ignição desligada' },
  { value: 'sos', label: 'SOS' },
  { value: 'offline', label: 'Offline' },
  { value: 'online', label: 'Online' },
  { value: 'battery', label: 'Bateria' },
  { value: 'jamming', label: 'Jamming' },
]

export default function AlertsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const type = searchParams.get('type') ?? ''
  const status = searchParams.get('status') ?? ''
  const severity = searchParams.get('severity') ?? ''
  const { can } = usePermissions()
  const acknowledge = useAcknowledgeAlert()
  const resolve = useResolveAlert()

  const query = useAlertsQuery({
    page,
    per_page: PER_PAGE,
    type: type || undefined,
    status: status || undefined,
    severity: severity || undefined,
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

  const columns: Array<Column<Alert>> = [
    {
      key: 'title',
      header: 'Alerta',
      render: (item) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{item.title}</p>
          <p className="truncate text-[13px] text-muted">{item.vehicle?.plate ?? '—'}</p>
        </div>
      ),
    },
    {
      key: 'type',
      header: 'Tipo',
      render: (item) => <span className="text-muted">{item.type_label ?? item.type}</span>,
    },
    {
      key: 'severity',
      header: 'Severidade',
      render: (item) => (
        <Badge
          variant={
            item.severity === 'critical'
              ? 'danger'
              : item.severity === 'high'
                ? 'warning'
                : item.severity === 'medium'
                  ? 'primary'
                  : 'neutral'
          }
        >
          {item.severity}
        </Badge>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (item) => (
        <Badge variant={item.status === 'open' ? 'warning' : item.status === 'resolved' ? 'success' : 'neutral'}>
          {item.status}
        </Badge>
      ),
    },
    {
      key: 'when',
      header: 'Data/hora',
      render: (item) => (
        <span className="text-muted">
          {item.occurred_at ? new Date(item.occurred_at).toLocaleString('pt-BR') : '—'}
        </span>
      ),
    },
    ...(can(Permission.ALERT_MANAGE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-40 text-right',
            render: (item: Alert) => (
              <div className="flex justify-end gap-1">
                {item.status === 'open' && (
                  <Button size="sm" variant="secondary" onClick={() => acknowledge.mutate(item.id)}>
                    Reconhecer
                  </Button>
                )}
                {item.status !== 'resolved' && (
                  <Button size="sm" variant="ghost" onClick={() => resolve.mutate(item.id)}>
                    Resolver
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Alert>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Alertas"
        description="Histórico de alertas gerados pelo rastreamento."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Alertas' }]}
      />
      <PageContent>
        <FilterBar>
          <Select
            aria-label="Filtrar por tipo"
            className="w-52"
            value={type}
            onChange={(event) => setFilter('type', event.target.value)}
            options={TYPE_OPTIONS}
          />
          <Select
            aria-label="Filtrar por status"
            className="w-48"
            value={status}
            onChange={(event) => setFilter('status', event.target.value)}
            options={[
              { value: '', label: 'Todos os status' },
              { value: 'open', label: 'Abertos' },
              { value: 'acknowledged', label: 'Reconhecidos' },
              { value: 'resolved', label: 'Resolvidos' },
            ]}
          />
          <Select
            aria-label="Filtrar por severidade"
            className="w-48"
            value={severity}
            onChange={(event) => setFilter('severity', event.target.value)}
            options={[
              { value: '', label: 'Todas as severidades' },
              { value: 'low', label: 'Low' },
              { value: 'medium', label: 'Medium' },
              { value: 'high', label: 'High' },
              { value: 'critical', label: 'Critical' },
            ]}
          />
        </FilterBar>

        <DataTable
          caption="Lista de alertas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={BellRing}
              title="Nenhum alerta"
              description="Alertas aparecerão conforme as regras configuradas."
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
