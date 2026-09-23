import { useState } from 'react'
import { FileBarChart, MapPin, Route as RouteIcon, Timer } from 'lucide-react'
import {
  Alert,
  Card,
  CardContent,
  CardHeader,
  DataTable,
  DateRangeFilter,
  EmptyState,
  Loading,
  Page,
  PageContent,
  PageHeader,
  SearchSelect,
  Section,
  Select,
  type Column,
} from '@/shared/design-system'
import { formatDateTime } from '@/shared/utils/format'
import { escapeHtml, printReportHtml } from '@/shared/utils/report-export'
import { resolvePresetRange } from '@/shared/utils/date-range'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useSessionStore } from '@/shared/stores/session.store'
import { clientsService } from '@/modules/clients/services/clients.service'
import { vehiclesService } from '@/modules/vehicles/services/vehicles.service'
import { useTrackingHistoryQuery } from '@/modules/tracking/hooks/useTracking'
import {
  formatDuration as formatRouteDuration,
  formatMeters,
  rangeToApiBounds,
  summarizeRoute,
} from '@/modules/tracking/lib/tracking'
import {
  useCommandsReportQuery,
  useEventsReportQuery,
  usePositionsReportQuery,
  useStopsReportQuery,
  useTripsReportQuery,
} from '../hooks/useReports'
import { reportsService } from '../services/reports.service'
import { ReportExportButtons } from '../components/ReportExportButtons'
import { RouteReportMap } from '../components/RouteReportMap'
import { TripsReport } from '../components/TripsReport'
import type {
  CommandReportRow,
  EventReportRow,
  PositionReportRow,
  StopReportRow,
} from '@/shared/types/models'

type ReportType = 'commands' | 'positions' | 'stops' | 'routes' | 'trips' | 'events'

const REPORT_OPTIONS = [
  { value: 'commands', label: 'Comandos Enviados' },
  { value: 'positions', label: 'Histórico de Posições' },
  { value: 'stops', label: 'Paradas' },
  { value: 'events', label: 'Eventos de Velocidade' },
  { value: 'routes', label: 'Rotas' },
  { value: 'trips', label: 'Percursos' },
]

async function loadClientOptions(search: string) {
  const response = await clientsService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((client) => ({ value: client.id, label: client.name }))
}

async function resolveClientLabel(value: string) {
  try {
    const client = await clientsService.get(value)
    return { value: client.id, label: client.name }
  } catch {
    return null
  }
}

function makeLoadVehicleOptions(clientId: string) {
  return async (search: string) => {
    const response = await vehiclesService.list({
      search: search || undefined,
      client_id: clientId || undefined,
      per_page: 20,
    })
    return response.data.map((vehicle) => ({
      value: vehicle.id,
      label: `${vehicle.plate}${vehicle.model ? ` · ${vehicle.model}` : ''}`,
    }))
  }
}

async function resolveVehicleLabel(value: string) {
  try {
    const vehicle = await vehiclesService.get(value)
    return {
      value: vehicle.id,
      label: `${vehicle.plate}${vehicle.model ? ` · ${vehicle.model}` : ''}`,
    }
  } catch {
    return null
  }
}

const COMMAND_COLUMNS: Array<Column<CommandReportRow>> = [
  { key: 'equipment_imei', header: 'Equipamento (IMEI)', render: (row) => row.equipment_imei ?? '—' },
  { key: 'vehicle', header: 'Veículo (Placa - Modelo)', render: (row) => row.vehicle ?? '—' },
  { key: 'command_label', header: 'Comando', render: (row) => row.command_label },
  {
    key: 'requested_at',
    header: 'Data do evento',
    render: (row) => formatDateTime(row.requested_at),
  },
  { key: 'user', header: 'Usuário', render: (row) => row.user ?? '—' },
  { key: 'status_label', header: 'Situação do envio', render: (row) => row.status_label },
]

function yesNo(value: boolean | null): string {
  if (value === null) return '—'
  return value ? 'Sim' : 'Não'
}

function formatStopDuration(seconds: number): string {
  if (seconds < 60) return `${seconds}s`

  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)

  if (hours === 0) return `${minutes}min`

  return `${hours}h ${minutes}min`
}

const POSITION_COLUMNS: Array<Column<PositionReportRow>> = [
  { key: 'gps_date', header: 'Data GPS', render: (row) => formatDateTime(row.gps_date) },
  { key: 'gprs_date', header: 'Data (GPRS)', render: (row) => formatDateTime(row.gprs_date) },
  { key: 'speed', header: 'Velocidade (Km)', render: (row) => (row.speed != null ? String(row.speed) : '—') },
  { key: 'ignition', header: 'Ignição', render: (row) => yesNo(row.ignition) },
  { key: 'driver', header: 'Motorista', render: (row) => row.driver ?? '—' },
  { key: 'gps_status', header: 'Status GPS', render: (row) => (row.gps_status == null ? '—' : row.gps_status ? 'Válido' : 'Inválido') },
  { key: 'gprs_status', header: 'Status GPRS', render: (row) => row.gprs_status ?? '—' },
  { key: 'location', header: 'Localização', render: (row) => row.location ?? '—' },
  { key: 'address', header: 'Endereço', render: (row) => row.address ?? '—' },
  { key: 'event_type', header: 'Tipo do Evento', render: (row) => row.event_type ?? '—' },
  { key: 'output', header: 'Saída', render: (row) => row.output ?? '—' },
  { key: 'input', header: 'Entrada', render: (row) => row.input ?? '—' },
  { key: 'package', header: 'Pacote', render: (row) => row.package ?? '—' },
  { key: 'period_odometer', header: 'Odômetro do período (Km)', render: (row) => (row.period_odometer != null ? String(row.period_odometer) : '—') },
  { key: 'period_horimeter', header: 'Horímetro do período', render: (row) => (row.period_horimeter != null ? String(row.period_horimeter) : '—') },
  { key: 'onboard_horimeter', header: 'Horímetro embarcado', render: (row) => (row.onboard_horimeter != null ? String(row.onboard_horimeter) : '—') },
  { key: 'onboard_odometer', header: 'Odômetro embarcado (Km)', render: (row) => (row.onboard_odometer != null ? String(row.onboard_odometer) : '—') },
  { key: 'battery', header: 'Bateria %', render: (row) => (row.battery != null ? `${row.battery}%` : '—') },
  { key: 'image', header: 'Imagem', render: (row) => (row.image ? <a href={row.image} target="_blank" rel="noreferrer" className="text-primary hover:underline">Ver imagem</a> : '—') },
  { key: 'voltage', header: 'Tensão (V)', render: (row) => (row.voltage != null ? `${row.voltage}V` : '—') },
  { key: 'blocked', header: 'Bloqueado', render: (row) => yesNo(row.blocked) },
]

const EVENT_COLUMNS: Array<Column<EventReportRow>> = [
  {
    key: 'date',
    header: 'Data',
    render: (row) =>
      row.date
        ? new Date(`${row.date}T12:00:00`).toLocaleDateString('pt-BR')
        : '—',
  },
  { key: 'plate', header: 'Placa', render: (row) => row.plate ?? '—' },
  { key: 'vehicle', header: 'Veículo', render: (row) => row.vehicle || '—' },
  {
    key: 'max_speed_kmh',
    header: 'Velocidade máxima atingida (Km)',
    render: (row) => (row.max_speed_kmh != null ? String(row.max_speed_kmh) : '—'),
  },
  {
    key: 'excess_count',
    header: 'Qtde velocidades excedidas',
    render: (row) => String(row.excess_count),
  },
]

const STOP_COLUMNS: Array<Column<StopReportRow>> = [
  { key: 'vehicle', header: 'Veículo (Placa - Modelo)', render: (row) => row.vehicle },
  {
    key: 'total_stop_seconds',
    header: 'Tempo total de paradas',
    render: (row) => formatStopDuration(row.total_stop_seconds),
  },
  { key: 'total_stops', header: 'Número total de paradas', render: (row) => String(row.total_stops) },
]

export default function ReportsPage() {
  const [type, setType] = useState<ReportType>('commands')
  const [from, setFrom] = useState(() => resolvePresetRange('today').from)
  const [to, setTo] = useState(() => resolvePresetRange('today').to)
  const [clientId, setClientId] = useState('')
  const [vehicleId, setVehicleId] = useState('')

  const user = useSessionStore((state) => state.user)
  const isClientUser = Boolean(user?.client_id)
  const { can } = usePermissions()
  const canViewEquipmentDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const commandColumns = canViewEquipmentDetails
    ? COMMAND_COLUMNS
    : COMMAND_COLUMNS.filter((column) => column.key !== 'equipment_imei')

  const baseFilters = {
    from: from || undefined,
    to: to || undefined,
    client_id: clientId || undefined,
  }

  const commandsQuery = useCommandsReportQuery(
    { ...baseFilters, vehicle_id: vehicleId || undefined },
    type === 'commands',
  )
  const positionsQuery = usePositionsReportQuery(
    vehicleId ? { ...baseFilters, vehicle_id: vehicleId } : null,
    type === 'positions',
  )
  const stopsQuery = useStopsReportQuery(baseFilters, type === 'stops')
  const routeBounds = rangeToApiBounds({ from, to })
  const routesQuery = useTrackingHistoryQuery(
    vehicleId,
    routeBounds?.from ?? '',
    routeBounds?.to ?? '',
    type === 'routes',
  )
  const tripsQuery = useTripsReportQuery(
    vehicleId ? { ...baseFilters, vehicle_id: vehicleId } : null,
    type === 'trips',
  )
  const eventsQuery = useEventsReportQuery(
    { ...baseFilters, vehicle_id: vehicleId || undefined },
    type === 'events',
  )

  const isPositions = type === 'positions'
  const isStops = type === 'stops'
  const isEvents = type === 'events'
  const isRoutes = type === 'routes'
  const isTrips = type === 'trips'
  const showVehicleFilter = type !== 'stops'
  const vehicleRequired = isPositions || isRoutes || isTrips
  const needsVehicle = vehicleRequired && !vehicleId

  const rows = isPositions
    ? (positionsQuery.data?.rows ?? [])
    : isStops
      ? (stopsQuery.data?.rows ?? [])
      : isEvents
        ? (eventsQuery.data?.rows ?? [])
        : (commandsQuery.data?.rows ?? [])
  const loading = isPositions
    ? positionsQuery.isPending
    : isStops
      ? stopsQuery.isPending
      : isEvents
        ? eventsQuery.isPending
        : commandsQuery.isPending

  const route = routesQuery.data ?? []
  const routeStats = summarizeRoute(route)

  const handleExportPdf = () => {
    if (isPositions) {
      const body = buildPositionsReportHtml(positionsQuery.data?.vehicle ?? '', positionsQuery.data?.rows ?? [])
      printReportHtml('Histórico de Posições', body)
      return
    }

    if (isStops) {
      printReportHtml('Paradas', buildStopsReportHtml(stopsQuery.data?.rows ?? []))
      return
    }

    printReportHtml(
      'Comandos Enviados',
      buildCommandsReportHtml(commandsQuery.data?.rows ?? [], canViewEquipmentDetails),
    )
  }

  const handleExportXlsx = () => {
    if (isPositions && vehicleId) {
      return reportsService.positionsExport({ ...baseFilters, vehicle_id: vehicleId })
    }
    if (isStops) {
      return reportsService.stopsExport(baseFilters)
    }
    return reportsService.commandsExport({ ...baseFilters, vehicle_id: vehicleId || undefined })
  }

  return (
    <Page>
      <PageHeader
        title="Relatórios"
        description="Consulte e exporte relatórios operacionais."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Relatórios' },
        ]}
      />

      <PageContent className="space-y-6">
        <Card>
          <CardHeader
            title="Selecionar relatório"
            description="Escolha o relatório e preencha os filtros desejados."
          />
          <CardContent className="space-y-6">
            <Select
              aria-label="Relatório"
              className="w-full sm:w-72"
              value={type}
              onChange={(event) => setType(event.target.value as ReportType)}
              options={REPORT_OPTIONS}
            />

            <Section title="Filtros">
              <div className="grid gap-4 sm:grid-cols-3">
                <DateRangeFilter from={from} to={to} onChange={({ from, to }) => { setFrom(from); setTo(to) }} />
                {!isClientUser && (
                  <SearchSelect
                    label="Cliente"
                    placeholder="Todos os clientes"
                    value={clientId}
                    onChange={(value) => {
                      setClientId(value)
                      setVehicleId('')
                    }}
                    loadOptions={loadClientOptions}
                    resolveLabel={resolveClientLabel}
                  />
                )}
                {showVehicleFilter && (
                  <SearchSelect
                    label="Veículo"
                    required={vehicleRequired}
                    placeholder={vehicleRequired ? 'Selecione um veículo' : 'Todos os veículos'}
                    value={vehicleId}
                    onChange={setVehicleId}
                    loadOptions={makeLoadVehicleOptions(isClientUser ? user?.client_id ?? '' : clientId)}
                    resolveLabel={resolveVehicleLabel}
                  />
                )}
              </div>
            </Section>

            {!isRoutes && !isTrips && !isEvents && (
              <div className="flex flex-wrap justify-end gap-2">
                <ReportExportButtons
                  onExportXlsx={handleExportXlsx}
                  onExportPdf={handleExportPdf}
                  disabled={rows.length === 0 || needsVehicle}
                />
              </div>
            )}
          </CardContent>
        </Card>

        {vehicleRequired && needsVehicle && (
          <Alert variant="info" title="Veículo obrigatório">
            {isRoutes
              ? 'Para visualizar a rota, selecione um veículo no filtro acima.'
              : isTrips
                ? 'Para visualizar os percursos, selecione um veículo no filtro acima.'
                : 'Para consultar o histórico de posições, selecione um veículo no filtro acima.'}
          </Alert>
        )}

        {isRoutes ? (
          <Card>
            <CardContent>
              {needsVehicle ? (
                <EmptyState
                  icon={RouteIcon}
                  title="Selecione um veículo"
                  description="Escolha um veículo e um período para exibir a rota no mapa."
                />
              ) : routesQuery.isPending ? (
                <div className="flex h-[420px] items-center justify-center">
                  <Loading label="Carregando percurso..." />
                </div>
              ) : route.length === 0 ? (
                <EmptyState
                  icon={RouteIcon}
                  title="Sem rota"
                  description="Não há posições registradas para o veículo e período selecionados."
                />
              ) : (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <RouteSummary label="Distância" value={formatMeters(routeStats.distanceMeters)} />
                    <RouteSummary label="Tempo" value={formatRouteDuration(routeStats.durationMs)} />
                    <RouteSummary label="Paradas" value={String(routeStats.stops)} />
                    <RouteSummary
                      label="Vel. máxima"
                      value={
                        routeStats.maxSpeedKmh == null
                          ? 'Não informado'
                          : `${routeStats.maxSpeedKmh.toLocaleString('pt-BR', { maximumFractionDigits: 0 })} km/h`
                      }
                    />
                  </div>
                  <RouteReportMap route={route} />
                </div>
              )}
            </CardContent>
          </Card>
        ) : isTrips ? (
          <Card>
            <CardContent>
              {needsVehicle ? (
                <EmptyState
                  icon={RouteIcon}
                  title="Selecione um veículo"
                  description="Escolha um veículo e um período para visualizar os percursos."
                />
              ) : (
                <TripsReport data={tripsQuery.data} loading={tripsQuery.isPending} />
              )}
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardContent>
              {isPositions ? (
                <DataTable
                  caption="Histórico de Posições"
                  columns={POSITION_COLUMNS}
                  rows={rows as PositionReportRow[]}
                  rowKey={(row) => row.id}
                  loading={loading}
                  emptyState={
                    <EmptyState
                      icon={MapPin}
                      title="Sem posições"
                      description="Não há posições registradas para os filtros selecionados."
                    />
                  }
                />
              ) : isStops ? (
                <DataTable
                  caption="Paradas"
                  columns={STOP_COLUMNS}
                  rows={rows as StopReportRow[]}
                  rowKey={(row) => row.vehicle_id}
                  loading={loading}
                  emptyState={
                    <EmptyState
                      icon={Timer}
                      title="Sem paradas"
                      description="Não há paradas registradas para os filtros selecionados."
                    />
                  }
                />
              ) : isEvents ? (
                <DataTable
                  caption="Eventos de Velocidade"
                  columns={EVENT_COLUMNS}
                  rows={rows as EventReportRow[]}
                  rowKey={(row) => row.id}
                  loading={loading}
                  emptyState={
                    <EmptyState
                      icon={FileBarChart}
                      title="Sem eventos"
                      description="Não há excessos de velocidade consolidados para os filtros selecionados."
                    />
                  }
                />
              ) : (
                <DataTable
                  caption="Comandos Enviados"
                  columns={commandColumns}
                  rows={rows as CommandReportRow[]}
                  rowKey={(row) => row.id}
                  loading={loading}
                  emptyState={
                    <EmptyState
                      icon={FileBarChart}
                      title="Sem dados"
                      description="Não há comandos para os filtros selecionados."
                    />
                  }
                />
              )}
            </CardContent>
          </Card>
        )}
      </PageContent>
    </Page>
  )
}

function RouteSummary({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg bg-surface-2 px-3 py-2">
      <p className="text-[11px] text-muted">{label}</p>
      <p className="font-medium text-foreground">{value}</p>
    </div>
  )
}

function buildCommandsReportHtml(rows: CommandReportRow[], showEquipment = true): string {
  const labels = [
    ...(showEquipment ? ['Equipamento (IMEI)'] : []),
    'Veículo',
    'Comando',
    'Data do evento',
    'Usuário',
    'Situação',
  ]
  const headerCells = labels.map((label) => `<th>${escapeHtml(label)}</th>`).join('')

  const bodyRows = rows
    .map(
      (row) => `
      <tr>
        ${showEquipment ? `<td>${escapeHtml(row.equipment_imei)}</td>` : ''}
        <td>${escapeHtml(row.vehicle)}</td>
        <td>${escapeHtml(row.command_label)}</td>
        <td>${escapeHtml(formatDateTime(row.requested_at))}</td>
        <td>${escapeHtml(row.user)}</td>
        <td>${escapeHtml(row.status_label)}</td>
      </tr>`,
    )
    .join('')

  return `
    <h1>Comandos Enviados</h1>
    <p class="meta">${escapeHtml(`${rows.length} registro(s)`)}</p>
    <table>
      <thead><tr>${headerCells}</tr></thead>
      <tbody>${bodyRows}</tbody>
    </table>`
}

function buildPositionsReportHtml(vehicle: string, rows: PositionReportRow[]): string {
  const headers = POSITION_COLUMNS.map((col) => col.header)
  const headerCells = headers.map((label) => `<th>${escapeHtml(label)}</th>`).join('')

  const bodyRows = rows
    .map(
      (row) => `
      <tr>
        <td>${escapeHtml(formatDateTime(row.gps_date))}</td>
        <td>${escapeHtml(formatDateTime(row.gprs_date))}</td>
        <td>${escapeHtml(row.speed)}</td>
        <td>${escapeHtml(yesNo(row.ignition))}</td>
        <td>${escapeHtml(row.driver)}</td>
        <td>${escapeHtml(row.gps_status == null ? '—' : row.gps_status ? 'Válido' : 'Inválido')}</td>
        <td>${escapeHtml(row.gprs_status)}</td>
        <td>${escapeHtml(row.location)}</td>
        <td>${escapeHtml(row.address)}</td>
        <td>${escapeHtml(row.event_type)}</td>
        <td>${escapeHtml(row.output)}</td>
        <td>${escapeHtml(row.input)}</td>
        <td>${escapeHtml(row.package)}</td>
        <td>${escapeHtml(row.period_odometer)}</td>
        <td>${escapeHtml(row.period_horimeter)}</td>
        <td>${escapeHtml(row.onboard_horimeter)}</td>
        <td>${escapeHtml(row.onboard_odometer)}</td>
        <td>${escapeHtml(row.battery)}</td>
        <td>${escapeHtml(row.image)}</td>
        <td>${escapeHtml(row.voltage)}</td>
        <td>${escapeHtml(yesNo(row.blocked))}</td>
      </tr>`,
    )
    .join('')

  return `
    <h1>Histórico de Posições</h1>
    <p class="meta">${escapeHtml(`${vehicle} · ${rows.length} registro(s)`)}</p>
    <table>
      <thead><tr>${headerCells}</tr></thead>
      <tbody>${bodyRows}</tbody>
    </table>`
}

function buildStopsReportHtml(rows: StopReportRow[]): string {
  const headerCells = ['Veículo', 'Tempo total de paradas', 'Número total de paradas']
    .map((label) => `<th>${escapeHtml(label)}</th>`)
    .join('')

  const bodyRows = rows
    .map(
      (row) => `
      <tr>
        <td>${escapeHtml(row.vehicle)}</td>
        <td>${escapeHtml(formatStopDuration(row.total_stop_seconds))}</td>
        <td>${escapeHtml(String(row.total_stops))}</td>
      </tr>`,
    )
    .join('')

  return `
    <h1>Paradas</h1>
    <p class="meta">${escapeHtml(`${rows.length} veículo(s)`)}</p>
    <table>
      <thead><tr>${headerCells}</tr></thead>
      <tbody>${bodyRows}</tbody>
    </table>`
}
