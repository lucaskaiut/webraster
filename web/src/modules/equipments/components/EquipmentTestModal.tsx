import { Modal } from '@/shared/design-system'
import { DeviceCommandHistory } from '@/modules/tracking/components/DeviceCommandHistory'
import { VehicleCommandsPanel } from '@/modules/tracking/components/VehicleCommandsPanel'
import type { Equipment } from '@/shared/types/models'

/**
 * Teste direto no equipamento, sem exigir vínculo com veículo. Os comandos
 * disponíveis vêm da integração (Traccar) para o dispositivo em questão.
 */
export function EquipmentTestModal({
  equipment,
  open,
  onClose,
}: {
  equipment: Equipment | null
  open: boolean
  onClose: () => void
}) {
  if (!equipment) return null

  const label = equipment.imei ?? equipment.model ?? 'equipamento'

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={`Testar ${label}`}
      description="Envie comandos de teste diretamente ao rastreador, mesmo sem vínculo com um veículo."
      size="lg"
    >
      <div className="space-y-6">
        <VehicleCommandsPanel deviceId={equipment.id} />

        <div className="space-y-3">
          <h4 className="text-sm font-semibold text-foreground">Histórico de comandos</h4>
          <DeviceCommandHistory deviceId={equipment.id} />
        </div>
      </div>
    </Modal>
  )
}
