import type { EquipmentLiveItem, TrackingLiveVehicle } from '@/shared/types/models'

/**
 * Adapta um equipamento sem veículo ao formato dos indicadores de status,
 * reutilizando o mesmo componente da lista de veículos.
 */
export function equipmentLiveAsVehicle(item: EquipmentLiveItem): TrackingLiveVehicle {
  return {
    id: item.id,
    plate: item.imei ?? item.model ?? 'Equipamento',
    brand: null,
    model: item.model,
    color: null,
    year: null,
    vehicle_type: null,
    client_id: null,
    client: null,
    equipment: null,
    online: item.online,
    position: item.position,
  }
}
