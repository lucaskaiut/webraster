import { z } from 'zod'

export const poiSchema = z.object({
  client_id: z.string().min(1, 'Selecione o cliente'),
  poi_category_id: z.string().min(1, 'Selecione a categoria'),
  name: z.string().min(1, 'Informe o nome').max(150),
  description: z.string().optional(),
  latitude: z.number({ error: 'Selecione a posição no mapa' }).min(-90).max(90),
  longitude: z.number({ error: 'Selecione a posição no mapa' }).min(-180).max(180),
  address: z.string().optional(),
  is_active: z.boolean(),
})

export type PoiFormValues = z.infer<typeof poiSchema>
