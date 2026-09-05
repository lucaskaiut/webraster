import { z } from 'zod'

export const equipmentSchema = z.object({
  imei: z.string().min(1, 'Informe o IMEI').max(20, 'IMEI inválido'),
  model: z.string(),
  iccid: z.string(),
  carrier: z.string(),
  is_active: z.boolean(),
})

export type EquipmentFormValues = z.infer<typeof equipmentSchema>
