import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Cpu, Pencil, Plus, Trash2 } from 'lucide-react'
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
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import type { Equipment, TrackingLiveVehicle } from '@/shared/types/models'
import { formatDateTimeWithSeconds } from '@/shared/utils/format'
import { useTrackingLiveQuery } from '@/modules/tracking/hooks/useTracking'
import { VehicleStatusIndicators } from '@/modules/tracking/components/VehicleStatusIndicators'
import { useDeleteEquipment, useEquipmentsQuery } from '../hooks/useEquipments'

const PER_PAGE = 10

export default function EquipmentsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const availableOnly = searchParams.get('available') === '1'

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [equipmentToDelete, setEquipmentToDelete] = useState<Equipment | null>(null)
  const deleteEquipment = useDeleteEquipment()

  const query = useEquipmentsQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    available: availableOnly || undefined,
  })

  const canTrack = can(Permission.TRACKING_READ)
  const liveQuery = useTrackingLiveQuery(canTrack)
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

    return map
  }, [liveQuery.data])

  const updateParams = (next: { page?: number; search?: string; available?: boolean }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.available !== undefined) {
          next.available ? params.set('available', '1') : params.delete('available')
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
    if (!equipmentToDelete) return
    deleteEquipment.mutate(equipmentToDelete.id, { onSettled: () => setEquipmentToDelete(null) })
  }

  const canMutate = can(Permission.EQUIPMENT_UPDATE) || can(Permission.EQUIPMENT_DELETE)

  const columns: Array<Column<Equipment>> = [
    {
      key: 'imei',
      header: 'IMEI',
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
      render: (equipment) => <span className="text-muted">{equipment.carrier ?? '—'}</span>,
    },
    {
      key: 'iccid',
      header: 'ICCID',
      render: (equipment) => <span className="text-muted">{equipment.iccid ?? '—'}</span>,
    },
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
      render: (equipment) => (
        <Badge variant={equipment.is_active ? 'success' : 'neutral'}>
          {equipment.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
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
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (equipment: Equipment) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.EQUIPMENT_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/equipments/${equipment.id}/edit`)}
                    aria-label={`Editar ${equipment.imei}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.EQUIPMENT_DELETE) && !equipment.is_assigned && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setEquipmentToDelete(equipment)}
                    aria-label={`Excluir ${equipment.imei}`}
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
          <Can permission={Permission.EQUIPMENT_CREATE}>
            <ButtonLink to="/equipments/create">
              <Plus className="size-4" />
              Novo equipamento
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por IMEI, modelo, ICCID ou operadora..."
            aria-label="Buscar equipamentos"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
          <Button
            variant={availableOnly ? 'primary' : 'secondary'}
            size="sm"
            onClick={() => updateParams({ available: !availableOnly })}
          >
            {availableOnly ? 'Somente disponíveis' : 'Todos'}
          </Button>
        </FilterBar>

        <DataTable
          caption="Lista de equipamentos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(equipment) => equipment.id}
          loading={query.isPending}
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
                !debouncedSearch ? (
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
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
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
            Tem certeza que deseja excluir o equipamento <strong>{equipmentToDelete?.imei}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
