import { useState } from 'react'
import { Car, Trash2 } from 'lucide-react'
import {
  Button,
  Card,
  CardContent,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Section,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import type { Vehicle } from '@/shared/types/models'
import { VehicleForm } from '@/modules/vehicles/forms/VehicleForm'
import {
  useCreateVehicle,
  useDeleteVehicle,
  useVehiclesQuery,
} from '@/modules/vehicles/hooks/useVehicles'

export function ClientVehiclesSection({ clientId }: { clientId: string }) {
  const [vehicleToDelete, setVehicleToDelete] = useState<Vehicle | null>(null)
  const vehiclesQuery = useVehiclesQuery({ client_id: clientId, per_page: 50 })
  const createVehicle = useCreateVehicle()
  const deleteVehicle = useDeleteVehicle()

  const columns: Array<Column<Vehicle>> = [
    {
      key: 'plate',
      header: 'Veículo',
      render: (vehicle) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{vehicle.plate}</p>
          <p className="truncate text-[13px] text-muted">
            {[vehicle.brand, vehicle.model, vehicle.year].filter(Boolean).join(' · ') || '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-16 text-right',
      render: (vehicle) => (
        <div className="flex justify-end">
          <Can permission={Permission.VEHICLE_DELETE}>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setVehicleToDelete(vehicle)}
              aria-label={`Excluir ${vehicle.plate}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          </Can>
        </div>
      ),
    },
  ]

  return (
    <>
      <Card>
        <CardContent>
          <Section title="Veículos" description="Frota vinculada a este cliente.">
            <DataTable
              caption="Veículos do cliente"
              columns={columns}
              rows={vehiclesQuery.data?.data ?? []}
              rowKey={(vehicle) => vehicle.id}
              loading={vehiclesQuery.isPending}
              emptyState={
                <EmptyState
                  icon={Car}
                  title="Nenhum veículo cadastrado"
                  description="Cadastre os veículos que farão parte do pedido."
                />
              }
            />
          </Section>
        </CardContent>
      </Card>

      <Can permission={Permission.VEHICLE_CREATE}>
        <Section
          title="Novo veículo"
          description="Preencha os dados do veículo; ele será vinculado a este cliente."
        >
          <VehicleForm
            mode="create"
            fixedClientId={clientId}
            showNav={false}
            showCancel={false}
            submitLabel="Adicionar veículo"
            resetOnSuccess
            submitting={createVehicle.isPending}
            onSubmit={(payload) => createVehicle.mutateAsync(payload)}
          />
        </Section>
      </Can>

      <ConfirmDialog
        open={vehicleToDelete !== null}
        onClose={() => setVehicleToDelete(null)}
        onConfirm={() => {
          if (!vehicleToDelete) return
          deleteVehicle.mutate(vehicleToDelete.id, { onSettled: () => setVehicleToDelete(null) })
        }}
        loading={deleteVehicle.isPending}
        title="Excluir veículo"
        description={
          <>
            Tem certeza que deseja excluir <strong>{vehicleToDelete?.plate}</strong>? Esta ação não
            pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </>
  )
}
