import { useMemo, useState } from 'react'
import { Car, Link2, Trash2 } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  ConfirmDialog,
  DataTable,
  EmptyState,
  SearchSelect,
  Section,
  type Column,
  type SearchSelectOption,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import type { SharedVehicle, Vehicle } from '@/shared/types/models'
import { VehicleForm } from '@/modules/vehicles/forms/VehicleForm'
import {
  useCreateVehicle,
  useDeleteVehicle,
  useVehiclesQuery,
} from '@/modules/vehicles/hooks/useVehicles'
import { vehiclesService } from '@/modules/vehicles/services/vehicles.service'
import {
  useClientSharedVehiclesQuery,
  useRemoveClientVehicleShare,
  useShareClientVehicle,
} from '../hooks/useClients'

export function ClientVehiclesSection({ clientId }: { clientId: string }) {
  const [vehicleToDelete, setVehicleToDelete] = useState<Vehicle | null>(null)
  const [shareToRemove, setShareToRemove] = useState<SharedVehicle | null>(null)
  const [candidateId, setCandidateId] = useState('')
  const vehiclesQuery = useVehiclesQuery({ client_id: clientId, per_page: 100 })
  const sharesQuery = useClientSharedVehiclesQuery(clientId)
  const createVehicle = useCreateVehicle()
  const deleteVehicle = useDeleteVehicle()
  const shareVehicle = useShareClientVehicle(clientId)
  const removeShare = useRemoveClientVehicleShare(clientId)

  const shares = sharesQuery.data?.data ?? []
  const sharedVehicleIds = useMemo(
    () => new Set(shares.map((share) => share.vehicle_id).filter(Boolean) as string[]),
    [shares],
  )

  const loadCandidateOptions = async (search: string): Promise<SearchSelectOption[]> => {
    const response = await vehiclesService.list({
      search: search || undefined,
      per_page: 20,
      exclude_client_id: clientId,
    })

    return response.data
      .filter((vehicle) => !sharedVehicleIds.has(vehicle.id))
      .map((vehicle) => ({
        value: vehicle.id,
        label: vehicle.plate,
        description:
          [vehicle.brand, vehicle.model].filter(Boolean).join(' ') ||
          vehicle.client?.name ||
          'Veículo de outro cliente',
      }))
  }

  const handleShare = async () => {
    if (!candidateId) return

    await shareVehicle.mutateAsync(candidateId)
    setCandidateId('')
  }

  const columns: Array<Column<Vehicle>> = [
    {
      key: 'plate',
      header: 'Veículo',
      render: (vehicle) => {
        const shared = vehicle.client_id !== clientId

        return (
          <div className="min-w-0">
            <p className="flex items-center gap-2 truncate font-medium text-foreground">
              <span className="truncate">{vehicle.plate}</span>
              {shared && <Badge variant="primary">Compartilhado</Badge>}
            </p>
            <p className="truncate text-[13px] text-muted">
              {[vehicle.brand, vehicle.model, vehicle.year].filter(Boolean).join(' · ') || '—'}
              {shared && vehicle.client?.name ? ` · Proprietário: ${vehicle.client.name}` : ''}
            </p>
          </div>
        )
      },
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-24 text-right',
      render: (vehicle) => {
        const shared = vehicle.client_id !== clientId
        const share = shared
          ? shares.find((item) => item.vehicle_id === vehicle.id) ?? null
          : null

        return (
          <div className="flex justify-end">
            {shared ? (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setShareToRemove(share)}
                disabled={!share}
                aria-label={`Remover compartilhamento de ${vehicle.plate}`}
                title="Remover compartilhamento"
                className="text-danger hover:bg-danger-soft hover:text-danger"
              >
                <Link2 className="size-4" />
              </Button>
            ) : (
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
            )}
          </div>
        )
      },
    },
  ]

  return (
    <>
      <Card>
        <CardContent>
          <Section
            title="Veículos"
            description="Frota vinculada a este cliente, incluindo veículos de outros clientes compartilhados com ele. A propriedade dos compartilhados permanece com o cliente de origem."
          >
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

      <Can permission={Permission.CLIENT_UPDATE}>
        <Card>
          <CardContent className="space-y-4">
            <Section
              title="Compartilhar veículo de outro cliente"
              description="Associe um veículo cadastrado em outro cliente para que ele também apareça na visualização deste cliente, sem transferir a propriedade."
            >
              <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                <div className="flex-1">
                  <SearchSelect
                    value={candidateId}
                    onChange={(value) => setCandidateId(value)}
                    loadOptions={loadCandidateOptions}
                    placeholder="Buscar veículo por placa, modelo ou proprietário..."
                    emptyMessage="Nenhum veículo disponível para compartilhar"
                  />
                </div>
                <Button
                  type="button"
                  loading={shareVehicle.isPending}
                  disabled={!candidateId}
                  onClick={() => void handleShare().catch(() => undefined)}
                >
                  <Link2 className="size-4" aria-hidden="true" />
                  Compartilhar
                </Button>
              </div>
            </Section>

            {shares.length > 0 && (
              <Alert variant="info" title="Veículos compartilhados">
                <ul className="space-y-1">
                  {shares.map((share) => (
                    <li key={share.id} className="flex items-center justify-between gap-2">
                      <span>
                        <strong>{share.vehicle?.plate ?? '—'}</strong>
                        {share.vehicle?.client?.name ? ` · ${share.vehicle.client.name}` : ''}
                      </span>
                      <button
                        type="button"
                        className="text-xs font-medium text-primary"
                        onClick={() => setShareToRemove(share)}
                      >
                        Remover
                      </button>
                    </li>
                  ))}
                </ul>
              </Alert>
            )}
          </CardContent>
        </Card>
      </Can>

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

      <ConfirmDialog
        open={shareToRemove !== null}
        onClose={() => setShareToRemove(null)}
        onConfirm={() => {
          if (!shareToRemove) return
          removeShare.mutate(shareToRemove.id, { onSettled: () => setShareToRemove(null) })
        }}
        loading={removeShare.isPending}
        title="Remover compartilhamento"
        description={
          <>
            O veículo <strong>{shareToRemove?.vehicle?.plate ?? 'selecionado'}</strong> deixará de
            aparecer para este cliente. O cadastro original é preservado.
          </>
        }
        confirmLabel="Remover"
      />
    </>
  )
}
