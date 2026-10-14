import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Cpu, FlaskConical, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react'
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
import type { Equipment, TrackingLiveVehicle } from '@/shared/types/models'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { useTrackingLiveEquipmentsQuery, useTrackingLiveQuery } from '@/modules/tracking/hooks/useTracking'
import { VehicleStatusIndicators } from '@/modules/tracking/components/VehicleStatusIndicators'
import { useDeleteEquipment, useEquipmentsQuery } from '../hooks/useEquipments'
import { EquipmentTestModal } from '../components/EquipmentTestModal'
import { equipmentLiveAsVehicle } from '../lib/live'

const PER_PAGE_OPTIONS = [
  { value: '10', label: '10 registros' },
  { value: '50', label: '50 registros' },
  { value: '100', label: '100 registros' },
  { value: 'all', label: 'Todos os registros' },
]

function parsePerPage(value: string | null): number | 'all' {
  if (value === 'all') return 'all'

  const parsed = Number(value)

  return parsed === 50 || parsed === 100 ? parsed : 10
}

export default function EquipmentsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const perPage = parsePerPage(searchParams.get('per_page'))
  const { sort, sortParam, toggleSort } = useTableSort()
  const availableParam = searchParams.get('available')
  const statusParam = searchParams.get('is_active')

  const vehicleFilter =
    availableParam === '1' ? 'available' : availableParam === '0' ? 'assigned' : ''
  const statusFilter = statusParam === '1' ? 'active' : statusParam === '0' ? 'inactive' : ''

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [equipmentToDelete, setEquipmentToDelete] = useState<Equipment | null>(null)
  const [equipmentToTest, setEquipmentToTest] = useState<Equipment | null>(null)
  const deleteEquipment = useDeleteEquipment()

  const query = useEquipmentsQuery({
    page,
    per_page: perPage,
    search: debouncedSearch || undefined,
    available: availableParam === null ? undefined : availableParam === '1',
    is_active: statusParam === null ? undefined : statusParam === '1',
    sort: sortParam,
  })

  const canTrack = can(Permission.TRACKING_READ)
  const liveQuery = useTrackingLiveQuery(canTrack)
  const equipmentLiveQuery = useTrackingLiveEquipmentsQuery(canTrack)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (!canTrack) return

    const timer = window.setInterval(() => setNow(Date.now()), 30_000)

    return () => window.clearInterval(timer)
  }, [canTrack])

  const liveByEquipment = useMemo(() => {
    const map = new Map<string, TrackingLiveVehicle>()

    for (const item of liveQuery.data ?? []) {
      if (item.equipment) {
        map.set(item.equipment.id, item)
      }
    }

    // Equipamentos em estoque (sem veículo) também exibem posição e indicadores.
    for (const item of equipmentLiveQuery.data ?? []) {
      if (!map.has(item.id)) {
        map.set(item.id, equipmentLiveAsVehicle(item))
      }
    }

    return map
  }, [liveQuery.data, equipmentLiveQuery.data])

  const updateParams = (next: {
    page?: number
    search?: string
    available?: string
    status?: string
    perPage?: string
  }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          if (next.search) {
            params.set('search', next.search)
          } else {
            params.delete('search')
          }
          params.delete('page')
        }
        if (next.available !== undefined) {
          if (next.available) {
            params.set('available', next.available)
          } else {
            params.delete('available')
          }
          params.delete('page')
        }
        if (next.status !== undefined) {
          if (next.status) {
            params.set('is_active', next.status)
          } else {
            params.delete('is_active')
          }
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
        if (next.page !== undefined) {
          if (next.page > 1) {
            params.set('page', String(next.page))
          } else {
            params.delete('page')
          }
        }
        return params
      },
      { replace: true },
    )
  }

  const confirmDelete = () => {
    if (!equipmentToDelete) return
    deleteEquipment.mutate(equipmentToDelete.id, { onSettled: () => setEquipmentToDelete(null) })
  }

  const canMutate = can(Permission.EQUIPMENT_UPDATE) || can(Permission.EQUIPMENT_DELETE)
  const canViewDetails = can(Permission.EQUIPMENT_DETAILS_READ)
  const canTest = can(Permission.DEVICE_COMMANDS_SEND)
  const showActions = canMutate || canTest

  const columns: Array<Column<Equipment>> = [
    ...(canViewDetails
      ? ([
          {
            key: 'imei',
            header: 'IMEI',
            sortable: true,
            render: (equipment) => (
              <div className="min-w-0">
                <p className="truncate font-medium text-foreground">{equipment.imei}</p>
                <p className="truncate text-[13px] text-muted">{equipment.model ?? '—'}</p>
              </div>
            ),
          },
          {
            key: 'carrier',
            header: 'Operadora',
            sortable: true,
            render: (equipment) => <span className="text-muted">{equipment.carrier ?? '—'}</span>,
          },
          {
            key: 'line_number',
            header: 'Número da Linha',
            sortable: true,
            render: (equipment) => <span className="text-muted">{equipment.line_number ?? '—'}</span>,
          },
        ] satisfies Array<Column<Equipment>>)
      : []),
    {
      key: 'vehicle',
      header: 'Veículo',
      render: (equipment) => (
        <span className="text-muted">{equipment.vehicle?.plate ?? 'Disponível'}</span>
      ),
    },
    {
      key: 'last_connection',
      header: 'Última conexão',
      sortable: true,
      sortKey: 'traccar_last_update',
      render: (equipment) => (
        <span className="whitespace-nowrap text-muted">
          {formatDateTimeWithSeconds(equipment.traccar_last_update)}
        </span>
      ),
    },
    ...(canTrack
      ? [
          {
            key: 'last_position',
            header: 'Última posição',
            sortable: true,
            sortKey: 'last_position',
            render: (equipment: Equipment) => (
              <span className="whitespace-nowrap text-muted">
                {formatDateTimeWithSeconds(liveByEquipment.get(equipment.id)?.position?.recorded_at)}
              </span>
            ),
          } satisfies Column<Equipment>,
        ]
      : []),
    {
      key: 'is_active',
      header: 'Status',
      sortable: true,
      render: (equipment) => (
        <Badge variant={equipment.is_active ? 'success' : 'neutral'}>
          {equipment.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    {
      key: 'created_at',
      header: 'Cadastro',
      sortable: true,
      render: (equipment) => (
        <span className="whitespace-nowrap text-muted">
          {formatDateTimeWithSeconds(equipment.created_at)}
        </span>
      ),
    },
    ...(canTrack
      ? [
          {
            key: 'indicators',
            header: 'Indicadores',
            className: 'w-[120px]',
            render: (equipment: Equipment) => {
              const live = liveByEquipment.get(equipment.id)

              if (!live) {
                return <span className="text-muted">—</span>
              }

              return <VehicleStatusIndicators vehicle={live} now={now} perRow={6} />
            },
          } satisfies Column<Equipment>,
        ]
      : []),
    ...(showActions
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-28 text-right',
            render: (equipment: Equipment) => (
              <div className="flex items-center justify-end gap-1">
                {canTest && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setEquipmentToTest(equipment)}
                    aria-label={`Testar ${equipment.imei ?? 'equipamento'}`}
                    title="Testar equipamento"
                  >
                    <FlaskConical className="size-4" />
                  </Button>
                )}
                {can(Permission.EQUIPMENT_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/equipments/${equipment.id}/edit`)}
                    aria-label={`Editar ${equipment.imei ?? 'equipamento'}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.EQUIPMENT_DELETE) && !equipment.is_assigned && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setEquipmentToDelete(equipment)}
                    aria-label={`Excluir ${equipment.imei ?? 'equipamento'}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Equipment>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Equipamentos"
        description="Gerencie os rastreadores da frota."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Equipamentos' }]}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="secondary"
              onClick={() => void query.refetch()}
              loading={query.isFetching}
              aria-label="Atualizar lista de equipamentos"
            >
              <RefreshCw className="size-4" aria-hidden="true" />
              Atualizar
            </Button>
            {canViewDetails && (
              <Can permission={Permission.EQUIPMENT_CREATE}>
                <ButtonLink to="/equipments/create">
                  <Plus className="size-4" />
                  Novo equipamento
                </ButtonLink>
              </Can>
            )}
          </div>
        }
      />

      <PageContent variant="table">
        <FilterBar>
          <SearchInput
            placeholder={
              canViewDetails
                ? 'Buscar por IMEI, modelo, número da linha ou operadora...'
                : 'Buscar equipamentos...'
            }
            aria-label="Buscar equipamentos"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
          <Select
            aria-label="Filtrar por veículo"
            className="w-44"
            value={vehicleFilter}
            onChange={(event) =>
              updateParams({
                available:
                  event.target.value === 'available'
                    ? '1'
                    : event.target.value === 'assigned'
                      ? '0'
                      : '',
              })
            }
            options={[
              { value: '', label: 'Todos os veículos' },
              { value: 'assigned', label: 'Com veículo' },
              { value: 'available', label: 'Sem veículo' },
            ]}
          />
          <Select
            aria-label="Filtrar por status"
            className="w-40"
            value={statusFilter}
            onChange={(event) =>
              updateParams({
                status:
                  event.target.value === 'active'
                    ? '1'
                    : event.target.value === 'inactive'
                      ? '0'
                      : '',
              })
            }
            options={[
              { value: '', label: 'Todos os status' },
              { value: 'active', label: 'Ativos' },
              { value: 'inactive', label: 'Inativos' },
            ]}
          />
        </FilterBar>

        <DataTable fillHeight
          caption="Lista de equipamentos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(equipment) => equipment.id}
          loading={query.isPending}
          sort={sort}
          onSort={toggleSort}
          emptyState={
            <EmptyState
              icon={Cpu}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum equipamento cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro rastreador.'
              }
              action={
                !debouncedSearch && canViewDetails ? (
                  <Can permission={Permission.EQUIPMENT_CREATE}>
                    <ButtonLink to="/equipments/create">
                      <Plus className="size-4" />
                      Novo equipamento
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
            perPageOptions={PER_PAGE_OPTIONS}
            onPerPageChange={(value) =>
              updateParams({ perPage: value === 'all' ? 'all' : String(value) })
            }
          />
        )}
      </PageContent>

      <ConfirmDialog
        open={equipmentToDelete !== null}
        onClose={() => setEquipmentToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteEquipment.isPending}
        title="Excluir equipamento"
        description={
          <>
            Tem certeza que deseja excluir o equipamento{' '}
            <strong>{equipmentToDelete?.imei ?? 'selecionado'}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />

      <EquipmentTestModal
        equipment={equipmentToTest}
        open={equipmentToTest !== null}
        onClose={() => setEquipmentToTest(null)}
      />
    </Page>
  )
}
