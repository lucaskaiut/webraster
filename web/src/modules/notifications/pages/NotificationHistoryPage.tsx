import { useSearchParams } from 'react-router'
import { BellRing } from 'lucide-react'
import {
  Badge,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  Select,
  type Column,
} from '@/shared/design-system'
import { formatDateTime } from '@/shared/utils/format'
import type { NotificationDelivery, NotificationLog } from '@/shared/types/models'
import { useSentNotificationsQuery } from '../hooks/useNotifications'

const PER_PAGE = 20

const SOURCE_LABELS: Record<string, { label: string; variant: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' }> = {
  alert: { label: 'Alerta', variant: 'warning' },
  finance: { label: 'Financeiro', variant: 'primary' },
  manual: { label: 'Manual', variant: 'success' },
  service_order: { label: 'Ordem de serviço', variant: 'neutral' },
  system: { label: 'Sistema', variant: 'neutral' },
}

const STATUS_VARIANTS: Record<string, 'neutral' | 'primary' | 'success' | 'warning' | 'danger'> = {
  sent: 'primary',
  delivered: 'success',
  failed: 'danger',
}

function DeliveryBadges({ deliveries }: { deliveries: NotificationDelivery[] }) {
  if (deliveries.length === 0) {
    return <span className="text-muted">—</span>
  }

  return (
    <div className="flex flex-wrap gap-1">
      {deliveries.map((delivery) => (
        <Badge key={delivery.id} variant={STATUS_VARIANTS[delivery.status] ?? 'neutral'}>
          {delivery.channel_label}: {delivery.status_label}
        </Badge>
      ))}
    </div>
  )
}

export default function NotificationHistoryPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const source = searchParams.get('source') ?? ''
  const clicked = searchParams.get('clicked') ?? ''

  const query = useSentNotificationsQuery({
    page,
    per_page: PER_PAGE,
    source: source || undefined,
    clicked: clicked === 'true' ? true : clicked === 'false' ? false : undefined,
  })

  const columns: Array<Column<NotificationLog>> = [
    {
      key: 'created_at',
      header: 'Enviada em',
      render: (log) => <span className="text-muted">{formatDateTime(log.created_at)}</span>,
    },
    {
      key: 'user',
      header: 'Destinatário',
      render: (log) => (
        <div className="min-w-0">
          <p className="truncate text-foreground">{log.user?.name ?? '—'}</p>
          {log.user?.email && <p className="truncate text-[13px] text-muted">{log.user.email}</p>}
        </div>
      ),
    },
    {
      key: 'content',
      header: 'Notificação',
      render: (log) => (
        <div className="min-w-0 max-w-md">
          <p className="truncate text-foreground">{log.title}</p>
          {log.body && <p className="truncate text-[13px] text-muted">{log.body}</p>}
        </div>
      ),
    },
    {
      key: 'source',
      header: 'Origem',
      render: (log) => {
        const info = log.source ? SOURCE_LABELS[log.source] : null
        return info ? (
          <Badge variant={info.variant}>{info.label}</Badge>
        ) : (
          <Badge variant="neutral">{log.source ?? '—'}</Badge>
        )
      },
    },
    {
      key: 'deliveries',
      header: 'Canais',
      render: (log) => <DeliveryBadges deliveries={log.deliveries} />,
    },
    {
      key: 'clicked_at',
      header: 'Aberta',
      render: (log) =>
        log.clicked_at ? (
          <div>
            <Badge variant="success">Sim</Badge>
            <p className="mt-1 text-[13px] text-muted">{formatDateTime(log.clicked_at)}</p>
          </div>
        ) : (
          <span className="text-muted">—</span>
        ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Histórico de notificações"
        description="Notificações enviadas por destinatário, com canais e abertura."
        breadcrumb={[{ label: 'Notificações', to: '/notifications/sent' }, { label: 'Histórico' }]}
      />

      <PageContent>
        <div className="flex flex-wrap gap-2">
          <Select
            aria-label="Filtrar por origem"
            className="w-56"
            value={source}
            onChange={(event) =>
              setSearchParams(
                (params) => {
                  if (event.target.value) {
                    params.set('source', event.target.value)
                  } else {
                    params.delete('source')
                  }
                  params.delete('page')
                  return params
                },
                { replace: true },
              )
            }
            options={[
              { value: '', label: 'Todas as origens' },
              ...Object.entries(SOURCE_LABELS).map(([value, info]) => ({ value, label: info.label })),
            ]}
          />

          <Select
            aria-label="Filtrar por abertura"
            className="w-56"
            value={clicked}
            onChange={(event) =>
              setSearchParams(
                (params) => {
                  if (event.target.value) {
                    params.set('clicked', event.target.value)
                  } else {
                    params.delete('clicked')
                  }
                  params.delete('page')
                  return params
                },
                { replace: true },
              )
            }
            options={[
              { value: '', label: 'Abertas e não abertas' },
              { value: 'true', label: 'Abertas' },
              { value: 'false', label: 'Não abertas' },
            ]}
          />
        </div>

        <DataTable
          caption="Histórico de notificações"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(log) => log.id}
          loading={query.isPending}
          emptyState={<EmptyState icon={BellRing} title="Nenhuma notificação enviada" />}
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) =>
              setSearchParams(
                (params) => {
                  if (next > 1) {
                    params.set('page', String(next))
                  } else {
                    params.delete('page')
                  }
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
