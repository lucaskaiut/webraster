import { memo, useMemo } from 'react'
import { SearchInput, Select, Skeleton } from '@/shared/design-system'
import { cn } from '@/shared/utils/cn'
import type { TrackingLiveVehicle } from '@/shared/types/models'
import { AlertTriangle, ArrowDownUp } from 'lucide-react'
import { VehicleAlarmTooltip } from './VehicleAlarmTooltip'
import { VehicleStatusIndicators } from './VehicleStatusIndicators'
import {
  connectionStatus,
  fleetStats,
  formatSpeed,
  matchesFleetFilter,
  matchesSearch,
  motionStatus,
  vehicleAlarms,
  vehicleLabel,
  type FleetFilter,
  type FleetSort,
} from '../lib/tracking'

const FILTERS: Array<{ value: FleetFilter; label: string }> = [
  { value: 'all', label: 'Todos' },
  { value: 'with_alarm', label: 'Com alarme' },
  { value: 'online', label: 'Online' },
  { value: 'offline', label: 'Offline' },
  { value: 'moving', label: 'Em movimento' },
  { value: 'stopped', label: 'Parado' },
  { value: 'no_signal', label: 'Sem sinal' },
]

const PER_PAGE_OPTIONS: Array<{ value: string; label: string }> = [
  { value: '10', label: '10 registros' },
  { value: '50', label: '50 registros' },
  { value: '100', label: '100 registros' },
  { value: 'all', label: 'Todos os registros' },
]

function sortVehicles(vehicles: TrackingLiveVehicle[], sort: FleetSort): TrackingLiveVehicle[] {
  return [...vehicles].sort((left, right) => {
    const leftAt = left.position?.recorded_at ? new Date(left.position.recorded_at).getTime() : null
    const rightAt = right.position?.recorded_at ? new Date(right.position.recorded_at).getTime() : null

    if (leftAt === null && rightAt === null) return left.plate.localeCompare(right.plate)
    if (leftAt === null) return 1
    if (rightAt === null) return -1

    return sort === 'recent' ? rightAt - leftAt : leftAt - rightAt
  })
}

export type FleetSidebarProps = {
  vehicles: TrackingLiveVehicle[]
  search: string
  onSearch: (value: string) => void
  filter: FleetFilter
  onFilter: (value: FleetFilter) => void
  sort: FleetSort
  onSortChange: (value: FleetSort) => void
  perPage: number | 'all'
  onPerPageChange: (value: number | 'all') => void
  selectedId: string | null
  onSelect: (id: string) => void
  loading: boolean
  now: number
  className?: string
}

export function FleetSidebar({
  vehicles,
  search,
  onSearch,
  filter,
  onFilter,
  sort,
  onSortChange,
  perPage,
  onPerPageChange,
  selectedId,
  onSelect,
  loading,
  now,
  className,
}: FleetSidebarProps) {
  const stats = fleetStats(vehicles)
  const visible = useMemo(
    () =>
      sortVehicles(
        vehicles.filter(
          (vehicle) => matchesSearch(vehicle, search) && matchesFleetFilter(vehicle, filter),
        ),
        sort,
      ),
    [vehicles, search, filter, sort],
  )
  const limited = useMemo(
    () => (perPage === 'all' ? visible : visible.slice(0, perPage)),
    [visible, perPage],
  )

  return (
    <aside className={cn('flex min-h-0 flex-col rounded-xl bg-surface shadow-card', className)}>
      <div className="space-y-2.5 p-2.5">
        <SearchInput
          className="sm:max-w-none"
          placeholder="Placa, cliente ou IMEI"
          aria-label="Buscar veículos"
          value={search}
          onChange={(event) => onSearch(event.target.value)}
        />

        <div className="grid grid-cols-3 gap-1.5 text-center">
          <Stat label="Total" value={stats.total} />
          <Stat label="Online" value={stats.online} />
          <Stat label="Offline" value={stats.offline} />
        </div>
        <div className="grid grid-cols-2 gap-1.5 text-center">
          <Stat label="Em movimento" value={stats.moving} />
          <Stat label="Parados" value={stats.stopped} />
        </div>
        {stats.withAlarm > 0 && (
          <div className="flex items-center gap-2 px-1 text-xs text-warning">
            <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
            <span>
              {stats.withAlarm} veículo{stats.withAlarm === 1 ? '' : 's'} com alarme ativo
            </span>
          </div>
        )}

        <div className="flex flex-wrap gap-1.5">
          {FILTERS.map((item) => (
            <button
              key={item.value}
              type="button"
              onClick={() => onFilter(item.value)}
              className={cn(
                'rounded-full px-2 py-0.5 text-[11px] font-medium transition-colors',
                filter === item.value
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-surface-2 text-muted hover:text-foreground',
              )}
            >
              {item.label}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-1.5">
          <Select
            aria-label="Registros por página"
            className="h-8 flex-1 pl-2.5 text-xs"
            options={PER_PAGE_OPTIONS}
            value={perPage === 'all' ? 'all' : String(perPage)}
            onChange={(event) =>
              onPerPageChange(
                event.target.value === 'all' ? 'all' : Number(event.target.value) || 50,
              )
            }
          />
          <button
            type="button"
            onClick={() => onSortChange(sort === 'recent' ? 'oldest' : 'recent')}
            title={sort === 'recent' ? 'Mais recentes primeiro' : 'Mais antigos primeiro'}
            aria-label={sort === 'recent' ? 'Ordenar por mais antigos' : 'Ordenar por mais recentes'}
            className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted transition-colors hover:text-foreground"
          >
            <ArrowDownUp className="size-3.5" aria-hidden="true" />
          </button>
        </div>

        {visible.length > limited.length && (
          <p className="px-1 text-[11px] text-subtle">
            Exibindo {limited.length} de {visible.length} veículos
          </p>
        )}
      </div>

      <div className="min-h-0 flex-1 overflow-y-auto">
        {loading && vehicles.length === 0 ? (
          <div className="space-y-2 p-3">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-20 w-full" />
          </div>
        ) : limited.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-muted">
            {vehicles.length === 0
              ? 'Sua frota aparecerá aqui quando houver veículos cadastrados com equipamento.'
              : 'Nenhum veículo encontrado. Tente alterar os filtros ou a busca.'}
          </p>
        ) : (
          <ul className="space-y-0.5 px-1.5 pb-2">
            {limited.map((vehicle) => (
              <VehicleListItem
                key={vehicle.id}
                vehicle={vehicle}
                selected={vehicle.id === selectedId}
                now={now}
                onSelect={onSelect}
              />
            ))}
          </ul>
        )}
      </div>
    </aside>
  )
}

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <div className="rounded-lg bg-surface-2 px-2 py-1">
      <p className="text-[13px] font-semibold text-foreground">{value}</p>
      <p className="text-[10px] text-muted">{label}</p>
    </div>
  )
}

const VehicleListItem = memo(function VehicleListItem({
  vehicle,
  selected,
  now,
  onSelect,
}: {
  vehicle: TrackingLiveVehicle
  selected: boolean
  now: number
  onSelect: (id: string) => void
}) {
  const connection = connectionStatus(vehicle)
  const motion = motionStatus(vehicle)
  const alarms = vehicleAlarms(vehicle)

  return (
    <li>
      <button
        type="button"
        onClick={() => onSelect(vehicle.id)}
        className={cn(
          'group/item flex w-full flex-col gap-0.5 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-surface-2',
          selected && 'bg-surface-2',
          alarms.length > 0 && 'relative z-10',
        )}
      >
        <div className="flex items-center justify-between gap-2">
          <span className="flex min-w-0 items-center gap-1.5 font-medium text-foreground">
            <StatusDot status={connection} />
            <span className="truncate">{vehicle.plate}</span>
            <VehicleAlarmTooltip alarms={alarms} />
          </span>
          <span className="shrink-0 text-[11px] text-muted">
            {motion === 'moving' ? `Em movimento · ${formatSpeed(vehicle.position?.speed)}` : motion === 'stopped' ? 'Parado' : 'Sem posição'}
          </span>
        </div>
        <p className="truncate text-[12px] text-muted">
          {vehicleLabel(vehicle)}
          {vehicle.client?.name ? ` · ${vehicle.client.name}` : ''}
        </p>
        <VehicleStatusIndicators vehicle={vehicle} now={now} className="mt-0.5" />
      </button>
    </li>
  )
})

function StatusDot({ status }: { status: 'online' | 'offline' | 'no_position' }) {
  const title =
    status === 'online' ? 'Online' : status === 'offline' ? 'Offline' : 'Sem sinal'

  return (
    <span
      role="img"
      aria-label={title}
      title={title}
      className={cn(
        'size-1.5 shrink-0 rounded-full',
        status === 'online' && 'bg-success',
        status === 'offline' && 'bg-muted',
        status === 'no_position' && 'bg-warning',
      )}
    />
  )
}
