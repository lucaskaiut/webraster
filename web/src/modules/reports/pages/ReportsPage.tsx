import { useState } from 'react'
import { FileBarChart, MapPin } from 'lucide-react'
import {
  Alert,
  Card,
  CardContent,
  CardHeader,
  DataTable,
  DateRangeFilter,
  EmptyState,
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
import { useSessionStore } from '@/shared/stores/session.store'
import { clientsService } from '@/modules/clients/services/clients.service'
import { vehiclesService } from '@/modules/vehicles/services/vehicles.service'
import { useCommandsReportQuery, usePositionsReportQuery } from '../hooks/useReports'
import { reportsService } from '../services/reports.service'
import { ReportExportButtons } from '../components/ReportExportButtons'
import type { CommandReportRow, PositionReportRow } from '@/shared/types/models'

type ReportType = 'commands' | 'positions'

const REPORT_OPTIONS = [
  { value: 'commands', label: 'Comandos Enviados' },
  { value: 'positions', label: 'Histórico de Posições' },
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

export default function ReportsPage() {
  const [type, setType] = useState<ReportType>('commands')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [clientId, setClientId] = useState('')
  const [vehicleId, setVehicleId] = useState('')

  const user = useSessionStore((state) => state.user)
  const isClientUser = Boolean(user?.client_id)

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

  const isPositions = type === 'positions'
  const rows = isPositions ? (positionsQuery.data?.rows ?? []) : (commandsQuery.data?.rows ?? [])
  const loading = isPositions ? positionsQuery.isPending : commandsQuery.isPending

  const needsVehicle = isPositions && !vehicleId

  const handleExportPdf = () => {
    if (isPositions) {
      const body = buildPositionsReportHtml(positionsQuery.data?.vehicle ?? '', positionsQuery.data?.rows ?? [])
      printReportHtml('Histórico de Posições', body)
      return
    }

    printReportHtml('Comandos Enviados', buildCommandsReportHtml(commandsQuery.data?.rows ?? []))
  }

  const handleExportXlsx = () => {
    if (isPositions && vehicleId) {
      return reportsService.positionsExport({ ...baseFilters, vehicle_id: vehicleId })
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
                <SearchSelect
                  label="Veículo"
                  required={isPositions}
                  placeholder={isPositions ? 'Selecione um veículo' : 'Todos os veículos'}
                  value={vehicleId}
                  onChange={setVehicleId}
                  loadOptions={makeLoadVehicleOptions(isClientUser ? user?.client_id ?? '' : clientId)}
                  resolveLabel={resolveVehicleLabel}
                />
              </div>
            </Section>

            <div className="flex flex-wrap justify-end gap-2">
              <ReportExportButtons
                onExportXlsx={handleExportXlsx}
                onExportPdf={handleExportPdf}
                disabled={rows.length === 0 || needsVehicle}
              />
            </div>
          </CardContent>
        </Card>

        {isPositions && needsVehicle && (
          <Alert variant="info" title="Veículo obrigatório">
            Para consultar o histórico de posições, selecione um veículo no filtro acima.
          </Alert>
        )}

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
            ) : (
              <DataTable
                caption="Comandos Enviados"
                columns={COMMAND_COLUMNS}
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
      </PageContent>
    </Page>
  )
}

function buildCommandsReportHtml(rows: CommandReportRow[]): string {
  const headerCells = ['Equipamento (IMEI)', 'Veículo', 'Comando', 'Data do evento', 'Usuário', 'Situação']
    .map((label) => `<th>${escapeHtml(label)}</th>`)
    .join('')

  const bodyRows = rows
    .map(
      (row) => `
      <tr>
        <td>${escapeHtml(row.equipment_imei)}</td>
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
