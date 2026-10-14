import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Car, Download, Plus, RefreshCw } from 'lucide-react'
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
import { useTableSort } from '@/shared/hooks/useTableSort'
import type { TrackingLiveVehicle, Vehicle } from '@/shared/types/models'
import { formatDateTime, formatDateTimeWithSeconds } from '@/shared/utils/format'
import { downloadBlob } from '@/shared/utils/report-export'
import { toast } from '@/shared/stores/toast.store'
import { useTrackingLiveQuery } from '@/modules/tracking/hooks/useTracking'
import { VehicleStatusIndicators } from '@/modules/tracking/components/VehicleStatusIndicators'
import { TABLE_PER_PAGE_OPTIONS, parseTablePerPage } from '@/shared/constants/pagination'
import { VehicleListRowActions } from '../components/VehicleListRowActions'
import { useDeleteVehicle, useVehiclesQuery } from '../hooks/useVehicles'
import { vehiclesService } from '../services/vehicles.service'

export default function VehiclesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const perPage = parseTablePerPage(searchParams.get('per_page'))
  const clientId = searchParams.get('client_id') ?? undefined
  const lastSignal = searchParams.get('last_signal') ?? ''

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [vehicleToDelete, setVehicleToDelete] = useState<Vehicle | null>(null)
  const [exporting, setExporting] = useState(false)
  const deleteVehicle = useDeleteVehicle()
  const { sort, sortParam, toggleSort } = useTableSort()

  const query = useVehiclesQuery({
    page,
    per_page: perPage,
    search: debouncedSearch || undefined,
    client_id: clientId,
    sort: sortParam,
    last_signal: lastSignal || undefined,
  })

  const canTrack = can(Permission.TRACKING_READ)
  const liveQuery = useTrackingLiveQuery(canTrack)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (!canTrack) return

    const timer = window.setInterval(() => setNow(Date.now()), 30_000)

    return () => window.clearInterval(timer)
  }, [canTrack])

  const liveByVehicle = useMemo(() => {
    const map = new Map<string, TrackingLiveVehicle>()

    for (const item of liveQuery.data ?? []) {
      if (item.equipment) {
        map.set(item.id, item)
      }
    }

    return map
  }, [liveQuery.data])

  const updateParams = (next: {
    page?: number
    search?: string
    perPage?: string
    lastSignal?: string
  }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.perPage !== undefined) {
          if (next.perPage && next.perPage !== '10') {
            params.set('per_page', next.perPage)
          } else {
            params.delete('per_page')
          }
          params.delete('page')
        }
        if (next.lastSignal !== undefined) {
          if (next.lastSignal) {
            params.set('last_signal', next.lastSignal)
          } else {
            params.delete('last_signal')
          }
          params.delete('page')
        }
        if (next.page !== undefined) {
          next.page > 1 ? params.set('page', String(next.page)) : params.delete('page')
        }
        return params
      },
      { replace: true },
    )
  }

  const confirmDelete = () => {
    if (!vehicleToDelete) return
    deleteVehicle.mutate(vehicleToDelete.id, { onSettled: () => setVehicleToDelete(null) })
  }

  const handleExport = async () => {
    setExporting(true)

    try {
      const blob = await vehiclesService.export({
        search: debouncedSearch || undefined,
        client_id: clientId,
        sort: sortParam,
        last_signal: lastSignal || undefined,
      })
      downloadBlob(blob, 'veiculos.xlsx')
      toast.success('Exportação gerada', 'A lista de veículos foi exportada em XLSX.')
    } catch {
      toast.error('Não foi possível exportar a lista de veículos')
    } finally {
      setExporting(false)
    }
  }

  const canViewEquipmentDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const showActionsColumn =
    can(Permission.VEHICLE_UPDATE) ||
    can(Permission.VEHICLE_DELETE) ||
    can(Permission.TRACKING_READ) ||
    can(Permission.REPORT_VIEW) ||
    can(Permission.DRIVER_CREATE) ||
    can(Permission.DEVICE_COMMANDS_SEND) ||
    can(Permission.ALERT_READ)

  const columns: Array<Column<Vehicle>> = [
    {
      key: 'plate',
      header: 'Veículo',
      sortable: true,
      render: (vehicle) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{vehicle.plate}</p>
          <p className="truncate text-[13px] text-muted">
            {[vehicle.brand, vehicle.model].filter(Boolean).join(' ') || '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'client',
      header: 'Cliente',
      render: (vehicle) => <span className="text-muted">{vehicle.client?.name ?? '—'}</span>,
    },
    {
      key: 'year',
      header: 'Ano',
      sortable: true,
      render: (vehicle) => <span className="text-muted">{vehicle.year ?? '—'}</span>,
    },
    {
      key: 'equipment',
      header: 'Equipamento',
      render: (vehicle) => (
        <span className="text-muted">
          {!vehicle.equipment
            ? 'Sem equipamento'
            : canViewEquipmentDetails
              ? (vehicle.equipment.imei ?? 'Rastreador')
              : 'Rastreador'}
        </span>
      ),
    },
    {
      key: 'last_connection',
      header: 'Última conexão',
      sortable: true,
      sortKey: 'last_connection',
      render: (vehicle) => (
        <span className="whitespace-nowrap text-muted">
          {vehicle.equipment
            ? formatDateTimeWithSeconds(vehicle.equipment.traccar_last_update)
            : '—'}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'Status',
      sortable: true,
      render: (vehicle) => (
        <Badge variant={vehicle.is_active ? 'success' : 'neutral'}>
          {vehicle.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      header: 'Cadastro',
      sortable: true,
      render: (vehicle) => (
        <span className="whitespace-nowrap text-muted">{formatDateTime(vehicle.created_at)}</span>
      ),
    },
    ...(canTrack
      ? [
          {
            key: 'last_position',
            header: 'Última posição',
            sortable: true,
            sortKey: 'last_position',
            render: (vehicle: Vehicle) => (
              <span className="whitespace-nowrap text-muted">
                {formatDateTimeWithSeconds(liveByVehicle.get(vehicle.id)?.position?.recorded_at)}
              </span>
            ),
          } satisfies Column<Vehicle>,
          {
            key: 'indicators',
            header: 'Indicadores',
            className: 'w-[120px]',
            render: (vehicle: Vehicle) => {
              const live = liveByVehicle.get(vehicle.id)

              if (!vehicle.equipment || !live) {
                return <span className="text-muted">—</span>
              }

              return <VehicleStatusIndicators vehicle={live} now={now} perRow={6} />
            },
          } satisfies Column<Vehicle>,
        ]
      : []),
    ...(showActionsColumn
      ? [
          {
            key: 'actions',
            header: 'Ações',
            className: 'w-20 text-right',
            render: (vehicle: Vehicle) => (
              <div className="flex justify-end">
                <VehicleListRowActions
                  vehicle={vehicle}
                  onEdit={
                    can(Permission.VEHICLE_UPDATE)
                      ? () => navigate(`/vehicles/${vehicle.id}/edit`)
                      : undefined
                  }
                  onDelete={
                    can(Permission.VEHICLE_DELETE)
                      ? () => setVehicleToDelete(vehicle)
                      : undefined
                  }
                />
              </div>
            ),
          } satisfies Column<Vehicle>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Veículos"
        description="Gerencie a frota vinculada aos clientes."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Veículos' }]}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="secondary"
              onClick={() => void query.refetch()}
              loading={query.isFetching}
              aria-label="Atualizar lista de veículos"
            >
              <RefreshCw className="size-4" aria-hidden="true" />
              Atualizar
            </Button>
            <Button variant="secondary" onClick={handleExport} loading={exporting}>
              <Download className="size-4" aria-hidden="true" />
              Exportar
            </Button>
            <Can permission={Permission.VEHICLE_CREATE}>
              <ButtonLink to="/vehicles/create">
                <Plus className="size-4" />
                Novo veículo
              </ButtonLink>
            </Can>
          </div>
        }
      />

      <PageContent variant="table">
        <FilterBar>
          <SearchInput
            placeholder="Buscar por placa, IMEI do equipamento, chassi, marca, modelo ou cliente..."
            aria-label="Buscar veículos"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
          <Select
            aria-label="Filtrar por último sinal"
            className="w-52"
            value={lastSignal}
            onChange={(event) => updateParams({ lastSignal: event.target.value })}
            options={[
              { value: '', label: 'Todos os sinais' },
              { value: 'online', label: 'Online' },
              { value: 'offline_up_to_24h', label: 'Offline até 24h' },
              { value: 'offline_24h_to_36h', label: 'Offline de 24h a 36h' },
              { value: 'offline_over_36h', label: 'Offline há mais de 36h' },
            ]}
          />
        </FilterBar>

        <DataTable fillHeight
          caption="Lista de veículos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(vehicle) => vehicle.id}
          loading={query.isPending}
          sort={sort}
          onSort={toggleSort}
          emptyState={
            <EmptyState
              icon={Car}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum veículo cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro veículo da frota.'
              }
              action={
                !debouncedSearch ? (
                  <Can permission={Permission.VEHICLE_CREATE}>
                    <ButtonLink to="/vehicles/create">
                      <Plus className="size-4" />
                      Novo veículo
                    </ButtonLink>
                  </Can>
                ) : undefined
              }
            />
          }
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) => updateParams({ page: next })}
            perPage={perPage}
            perPageOptions={TABLE_PER_PAGE_OPTIONS}
            onPerPageChange={(value) =>
              updateParams({ perPage: value === 'all' ? 'all' : String(value) })
            }
          />
        )}
      </PageContent>

      <ConfirmDialog
        open={vehicleToDelete !== null}
        onClose={() => setVehicleToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteVehicle.isPending}
        title="Excluir veículo"
        description={
          <>
            Tem certeza que deseja excluir o veículo <strong>{vehicleToDelete?.plate}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
