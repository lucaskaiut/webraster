import { z } from 'zod'

export const equipmentSchema = z.object({
  imei: z
    .string()
    .trim()
    .regex(/^\d{1,16}$/, 'Informe o número do equipamento com até 16 dígitos, sem espaços'),
  model: z.string(),
  line_number: z.string(),
  carrier: z.string(),
  is_active: z.boolean(),
})

export type EquipmentFormValues = z.infer<typeof equipmentSchema>
