import { z } from 'zod'

export const vehicleSchema = z.object({
  client_id: z.string().min(1, 'Selecione o cliente'),
  plate: z.string().min(1, 'Informe a placa').max(10, 'Placa inválida'),
  chassis: z.string(),
  renavam: z.string(),
  brand: z.string(),
  model: z.string(),
  color: z.string(),
  year: z.string(),
  is_active: z.boolean(),
})

export type VehicleFormValues = z.infer<typeof vehicleSchema>
