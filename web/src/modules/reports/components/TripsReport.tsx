import { useEffect, useState } from 'react'
import { Route as RouteIcon } from 'lucide-react'
import { EmptyState, Loading } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import { formatDateTime } from '@/shared/utils/format'
import type { TripItem, TripReport, TripReportSummary } from '@/shared/types/models'
import { TripsReportMap } from './TripsReportMap'

function formatClock(seconds: number): string {
  const pad = (value: number) => String(value).padStart(2, '0')
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const rest = seconds % 60

  return `${pad(hours)}:${pad(minutes)}:${pad(rest)}`
}

function formatKm(meters: number): string {
  return `${(meters / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} Km`
}

export function TripsReport({ data, loading }: { data: TripReport | undefined; loading: boolean }) {
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const trips = data?.trips ?? []

  useEffect(() => {
    if (!data || data.trips.length === 0) return

    setSelectedId((current) =>
      current && data.trips.some((trip) => trip.id === current) ? current : data.trips[0].id,
    )
  }, [data])

  if (loading) {
    return (
      <div className="flex h-[420px] items-center justify-center">
        <Loading label="Carregando percursos..." />
      </div>
    )
  }

  if (trips.length === 0) {
    return (
      <EmptyState
        icon={RouteIcon}
        title="Nenhum percurso encontrado"
        description="Nenhum percurso encontrado para os filtros informados."
      />
    )
  }

  const selected = trips.find((trip) => trip.id === selectedId) ?? trips[0]

  return (
    <div className="space-y-4">
      <SummaryBar summary={data!.summary} />

      <div className="grid gap-4 lg:grid-cols-[minmax(0,360px)_1fr]">
        <div className="max-h-[640px] space-y-3 overflow-y-auto pr-1">
          {trips.map((trip, index) => (
            <TripCard
              key={trip.id}
              trip={trip}
              index={index}
              selected={trip.id === selected.id}
              onSelect={() => setSelectedId(trip.id)}
            />
          ))}
        </div>

        <div className="h-[640px]">
          <TripsReportMap trip={selected} />
        </div>
      </div>
    </div>
  )
}

function SummaryBar({ summary }: { summary: TripReportSummary }) {
  const items = [
    { label: 'Tempo em movimento', value: formatClock(summary.total_moving_seconds) },
    { label: 'Tempo parado', value: formatClock(summary.total_stopped_seconds) },
    { label: 'Ignição ligada', value: formatClock(summary.total_ignition_on_seconds) },
    { label: 'Ignição desligada', value: formatClock(summary.total_ignition_off_seconds) },
    { label: 'Quilometragem total', value: formatKm(summary.total_distance_meters) },
    { label: 'Percursos', value: String(summary.trips) },
  ]

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
      {items.map((item) => (
        <div key={item.label} className="rounded-lg bg-surface-2 px-3 py-2">
          <p className="text-[11px] text-muted">{item.label}</p>
          <p className="font-semibold text-foreground">{item.value}</p>
        </div>
      ))}
    </div>
  )
}

function TripCard({
  trip,
  index,
  selected,
  onSelect,
}: {
  trip: TripItem
  index: number
  selected: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        'w-full rounded-xl border p-3 text-left transition-colors',
        selected ? 'border-primary bg-primary-soft' : 'border-surface-3 bg-surface hover:bg-surface-2',
      )}
    >
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-muted">Percurso {index + 1}</span>
        <span className="text-sm font-semibold text-emerald-600">{formatClock(trip.moving_seconds)}</span>
      </div>

      <div className="mt-1 flex items-center justify-between text-xs">
        <span className="text-muted">Parado seguinte</span>
        <span className="font-medium text-rose-600">
          {trip.following_stop_seconds != null ? formatClock(trip.following_stop_seconds) : '—'}
        </span>
      </div>

      <p className="mt-1 text-sm font-medium text-foreground">{formatKm(trip.distance_meters)}</p>

      <p className="mt-1 text-xs text-muted">
        {formatDateTime(trip.start_at)} até {formatDateTime(trip.end_at)}
      </p>

      {trip.origin?.address && (
        <p className="mt-1 truncate text-xs text-muted">De: {trip.origin.address}</p>
      )}
      {trip.destination?.address && (
        <p className="truncate text-xs text-muted">Para: {trip.destination.address}</p>
      )}
      {trip.driver && <p className="mt-1 text-xs text-muted">Motorista: {trip.driver}</p>}
    </button>
  )
}
