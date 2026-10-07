import { equipmentsService } from '@/modules/equipments/services/equipments.service'

export async function loadAvailableEquipments(search: string) {
  const response = await equipmentsService.list({
    search: search || undefined,
    available: true,
    per_page: 20,
  })

  return response.data.map((item) => ({
    value: item.id,
    label: [item.imei, item.model].filter(Boolean).join(' · ') || 'Equipamento',
  }))
}
