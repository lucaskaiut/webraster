import { useMemo, useState } from 'react'
import {
  FileBarChart,
  Map as MapIcon,
  MapPinned,
  MoreHorizontal,
  Pencil,
  Share2,
  Terminal,
  Trash2,
  UserPlus,
} from 'lucide-react'
import {
  Button,
  Dropdown,
  DropdownItem,
  DropdownSeparator,
  Modal,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import type { Vehicle } from '@/shared/types/models'
import { DriverForm } from '@/modules/drivers/forms/DriverForm'
import { useCreateDriver } from '@/modules/drivers/hooks/useDrivers'
import { VehicleCommandsPanel } from '@/modules/tracking/components/VehicleCommandsPanel'
import { ShareTrackingModal } from '@/modules/tracking/components/ShareTrackingModal'
import { googleMapsLinks } from '@/modules/tracking/lib/google-maps'

function buildMonitoringUrl(vehicleId: string): string {
  const params = new URLSearchParams({ vehicle: vehicleId, only: '1' })

  return `/monitoring?${params.toString()}`
}

function buildPositionsReportUrl(vehicle: Vehicle): string {
  const params = new URLSearchParams({
    type: 'positions',
    vehicle_id: vehicle.id,
  })

  const clientId = vehicle.client_id ?? vehicle.client?.id
  if (clientId) {
    params.set('client_id', clientId)
  }

  if (vehicle.client?.name) {
    params.set('client_label', vehicle.client.name)
  }

  const vehicleLabel = [vehicle.plate, vehicle.model].filter(Boolean).join(' · ')
  if (vehicleLabel) {
    params.set('vehicle_label', vehicleLabel)
  }

  return `/reports?${params.toString()}`
}

export function VehicleListRowActions({
  vehicle,
  position,
  onEdit,
  onDelete,
}: {
  vehicle: Vehicle
  /** Última posição conhecida (rastreamento ao vivo), para o atalho do Google Maps. */
  position?: { latitude: number; longitude: number } | null
  onEdit?: () => void
  onDelete?: () => void
}) {
  const { can } = usePermissions()
  const createDriver = useCreateDriver()

  const [commandsOpen, setCommandsOpen] = useState(false)
  const [driverOpen, setDriverOpen] = useState(false)
  const [shareOpen, setShareOpen] = useState(false)

  const canEdit = can(Permission.VEHICLE_UPDATE) && onEdit
  const canDelete = can(Permission.VEHICLE_DELETE) && onDelete
  const canMonitor = can(Permission.TRACKING_READ)
  const canShare = can(Permission.TRACKING_SHARE)
  const canReport = can(Permission.REPORT_VIEW)
  const canCommands =
    can(Permission.DEVICE_COMMANDS_SEND) && Boolean(vehicle.equipment?.id)
  const canCreateDriver = can(Permission.DRIVER_CREATE) && Boolean(vehicle.client_id)
  const mapsUrl = canMonitor && position ? googleMapsLinks(position).place : null

  const hasMenu =
    canEdit ||
    canDelete ||
    canMonitor ||
    canShare ||
    canReport ||
    canCommands ||
    canCreateDriver ||
    mapsUrl !== null

  const shareVehicles = useMemo(
    () => [{ id: vehicle.id, plate: vehicle.plate, brand: vehicle.brand, model: vehicle.model }],
    [vehicle.id, vehicle.plate, vehicle.brand, vehicle.model],
  )

  if (!hasMenu) {
    return null
  }

  return (
    <>
      <Dropdown
        label={`Ações para ${vehicle.plate}`}
        trigger={
          <Button variant="ghost" size="sm" aria-label={`Ações para ${vehicle.plate}`}>
            <MoreHorizontal className="size-4" />
          </Button>
        }
      >
        {canEdit && (
          <DropdownItem icon={Pencil} onSelect={onEdit}>
            Editar
          </DropdownItem>
        )}
        {canMonitor && (
          <DropdownItem
            icon={MapPinned}
            onSelect={() => window.open(buildMonitoringUrl(vehicle.id), '_blank', 'noopener,noreferrer')}
          >
            Monitorar
          </DropdownItem>
        )}
        {mapsUrl && (
          <DropdownItem
            icon={MapIcon}
            onSelect={() => window.open(mapsUrl, '_blank', 'noopener,noreferrer')}
          >
            Ver no Google Maps
          </DropdownItem>
        )}
        {canShare && (
          <DropdownItem icon={Share2} onSelect={() => setShareOpen(true)}>
            Compartilhar localização
          </DropdownItem>
        )}
        {canReport && (
          <DropdownItem
            icon={FileBarChart}
            onSelect={() =>
              window.open(buildPositionsReportUrl(vehicle), '_blank', 'noopener,noreferrer')
            }
          >
            Histórico
          </DropdownItem>
        )}
        {canCreateDriver && (
          <DropdownItem icon={UserPlus} onSelect={() => setDriverOpen(true)}>
            Cadastrar motorista
          </DropdownItem>
        )}
        {canCommands && (
          <DropdownItem icon={Terminal} onSelect={() => setCommandsOpen(true)}>
            Comandos
          </DropdownItem>
        )}
        {canDelete && (
          <>
            <DropdownSeparator />
            <DropdownItem icon={Trash2} danger onSelect={onDelete}>
              Excluir
            </DropdownItem>
          </>
        )}
      </Dropdown>

      <Modal
        open={commandsOpen}
        onClose={() => setCommandsOpen(false)}
        title={`Comandos · ${vehicle.plate}`}
        description="Comandos padrão do rastreador via Traccar."
        size="md"
        footer={
          <Button type="button" variant="secondary" onClick={() => setCommandsOpen(false)}>
            Fechar
          </Button>
        }
      >
        {vehicle.equipment?.id ? (
          <VehicleCommandsPanel deviceId={vehicle.equipment.id} allowCustom={false} />
        ) : null}
      </Modal>

      {canShare && (
        <ShareTrackingModal
          open={shareOpen}
          onClose={() => setShareOpen(false)}
          initialVehicles={shareVehicles}
        />
      )}

      <Modal
        open={driverOpen}
        onClose={() => setDriverOpen(false)}
        title={`Motorista · ${vehicle.plate}`}
        description="O motorista será vinculado a este veículo e ao cliente da frota."
        size="lg"
      >
        <DriverForm
          mode="create"
          fixedClientId={vehicle.client_id}
          fixedVehicleId={vehicle.id}
          showCancel={false}
          submitLabel="Salvar motorista"
          submitting={createDriver.isPending}
          onSubmit={async (payload) => {
            await createDriver.mutateAsync(payload)
            setDriverOpen(false)
          }}
        />
      </Modal>
    </>
  )
}
