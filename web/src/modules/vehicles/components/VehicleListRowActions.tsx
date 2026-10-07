import { useState } from 'react'
import { useNavigate } from 'react-router'
import {
  FileBarChart,
  MapPinned,
  MoreHorizontal,
  Pencil,
  ScrollText,
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

function buildDeviceRawLogsUrl(vehicle: Vehicle): string | null {
  const equipmentId = vehicle.equipment?.id

  if (!equipmentId) {
    return null
  }

  const params = new URLSearchParams({ equipment: equipmentId })
  const label = [vehicle.equipment?.imei, vehicle.plate].filter(Boolean).join(' · ')

  if (label) {
    params.set('equipment_label', label)
  }

  return `/monitoring/device-logs?${params.toString()}`
}

export function VehicleListRowActions({
  vehicle,
  onEdit,
  onDelete,
}: {
  vehicle: Vehicle
  onEdit?: () => void
  onDelete?: () => void
}) {
  const { can } = usePermissions()
  const navigate = useNavigate()
  const createDriver = useCreateDriver()

  const [commandsOpen, setCommandsOpen] = useState(false)
  const [driverOpen, setDriverOpen] = useState(false)

  const canEdit = can(Permission.VEHICLE_UPDATE) && onEdit
  const canDelete = can(Permission.VEHICLE_DELETE) && onDelete
  const canMonitor = can(Permission.TRACKING_READ)
  const canReport = can(Permission.REPORT_VIEW)
  const canCommands =
    can(Permission.DEVICE_COMMANDS_SEND) && Boolean(vehicle.equipment?.id)
  const canCreateDriver = can(Permission.DRIVER_CREATE) && Boolean(vehicle.client_id)
  const deviceRawLogsUrl = buildDeviceRawLogsUrl(vehicle)
  const canDeviceRawLog = can(Permission.EQUIPMENT_DETAILS_READ) && deviceRawLogsUrl !== null

  const hasMenu =
    canEdit ||
    canDelete ||
    canMonitor ||
    canReport ||
    canCommands ||
    canCreateDriver ||
    canDeviceRawLog

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
        {canDeviceRawLog && deviceRawLogsUrl && (
          <DropdownItem icon={ScrollText} onSelect={() => navigate(deviceRawLogsUrl)}>
            Log do dispositivo
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
