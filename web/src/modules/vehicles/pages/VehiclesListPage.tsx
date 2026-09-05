import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Car, Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { Vehicle } from '@/shared/types/models'
import { useDeleteVehicle, useVehiclesQuery } from '../hooks/useVehicles'

const PER_PAGE = 10

export default function VehiclesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const clientId = searchParams.get('client_id') ?? undefined

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [vehicleToDelete, setVehicleToDelete] = useState<Vehicle | null>(null)
  const deleteVehicle = useDeleteVehicle()

  const query = useVehiclesQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    client_id: clientId,
  })

  const updateParams = (next: { page?: number; search?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
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

  const canMutate = can(Permission.VEHICLE_UPDATE) || can(Permission.VEHICLE_DELETE)

  const columns: Array<Column<Vehicle>> = [
    {
      key: 'plate',
      header: 'Veículo',
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
      render: (vehicle) => <span className="text-muted">{vehicle.year ?? '—'}</span>,
    },
    {
      key: 'equipment',
      header: 'Equipamento',
      render: (vehicle) => (
        <span className="text-muted">{vehicle.equipment?.imei ?? 'Sem equipamento'}</span>
      ),
    },
    {
      key: 'is_active',
      header: 'Status',
      render: (vehicle) => (
        <Badge variant={vehicle.is_active ? 'success' : 'neutral'}>
          {vehicle.is_active ? 'Ativo' : 'Inativo'}
        </Badge>
      ),
    },
    ...(canMutate
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (vehicle: Vehicle) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.VEHICLE_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/vehicles/${vehicle.id}/edit`)}
                    aria-label={`Editar ${vehicle.plate}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.VEHICLE_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setVehicleToDelete(vehicle)}
                    aria-label={`Excluir ${vehicle.plate}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
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
          <Can permission={Permission.VEHICLE_CREATE}>
            <ButtonLink to="/vehicles/create">
              <Plus className="size-4" />
              Novo veículo
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por placa, chassi, marca ou modelo..."
            aria-label="Buscar veículos"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de veículos"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(vehicle) => vehicle.id}
          loading={query.isPending}
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
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
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
