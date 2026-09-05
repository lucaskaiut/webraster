import { useSearchParams } from 'react-router'
import { History } from 'lucide-react'
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
import type { GeofenceEvent } from '@/shared/types/models'
import { useGeofenceEventsQuery } from '../hooks/useGeofences'

const PER_PAGE = 15

export default function GeofenceEventsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const type = searchParams.get('type') ?? ''

  const query = useGeofenceEventsQuery({
    page,
    per_page: PER_PAGE,
    type: type === 'entry' || type === 'exit' ? type : undefined,
  })

  const columns: Array<Column<GeofenceEvent>> = [
    {
      key: 'event',
      header: 'Evento',
      render: (item) => (
        <Badge variant={item.type === 'entry' ? 'success' : 'warning'}>
          {item.type === 'entry' ? 'Entrada' : 'Saída'}
        </Badge>
      ),
    },
    {
      key: 'vehicle',
      header: 'Veículo',
      render: (item) => <span className="font-medium text-foreground">{item.vehicle?.plate ?? '—'}</span>,
    },
    {
      key: 'geofence',
      header: 'Geocerca',
      render: (item) => <span className="text-muted">{item.geofence?.name ?? '—'}</span>,
    },
    {
      key: 'when',
      header: 'Data/hora',
      render: (item) => (
        <span className="text-muted">
          {item.recorded_at ? new Date(item.recorded_at).toLocaleString('pt-BR') : '—'}
        </span>
      ),
    },
    {
      key: 'coords',
      header: 'Posição',
      render: (item) => (
        <span className="text-[13px] text-muted">
          {item.latitude.toFixed(5)}, {item.longitude.toFixed(5)}
        </span>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Eventos de geocerca"
        description="Histórico de entradas e saídas detectadas pelo rastreamento."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Geocercas', to: '/geofences' },
          { label: 'Eventos' },
        ]}
      />
      <PageContent>
        <div className="flex gap-2">
          <Select
            aria-label="Filtrar por tipo"
            className="w-52"
            value={type}
            onChange={(event) =>
              setSearchParams(
                (params) => {
                  event.target.value ? params.set('type', event.target.value) : params.delete('type')
                  params.delete('page')
                  return params
                },
                { replace: true },
              )
            }
            options={[
              { value: '', label: 'Todos os tipos' },
              { value: 'entry', label: 'Entrada' },
              { value: 'exit', label: 'Saída' },
            ]}
          />
        </div>

        <DataTable
          caption="Eventos de geocerca"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(item) => item.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={History}
              title="Nenhum evento"
              description="Eventos aparecem quando veículos entram ou saem de geocercas ativas."
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
