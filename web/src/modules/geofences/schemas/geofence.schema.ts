import { z } from 'zod'

export const geofenceSchema = z
  .object({
    client_id: z.string().min(1, 'Selecione o cliente'),
    name: z.string().min(1, 'Informe o nome').max(150),
    description: z.string().optional(),
    type: z.enum(['circle', 'polygon']),
    is_active: z.boolean(),
    center_latitude: z.number().optional().nullable(),
    center_longitude: z.number().optional().nullable(),
    radius_meters: z.coerce.number().int().positive().max(100000).optional().nullable(),
    geometry: z
      .array(
        z.object({
          latitude: z.number().min(-90).max(90),
          longitude: z.number().min(-180).max(180),
        }),
      )
      .optional()
      .nullable(),
  })
  .superRefine((values, ctx) => {
    if (values.type === 'circle') {
      if (values.center_latitude == null || values.center_longitude == null) {
        ctx.addIssue({ code: 'custom', message: 'Selecione o centro no mapa', path: ['center_latitude'] })
      }
      if (values.radius_meters == null || values.radius_meters <= 0) {
        ctx.addIssue({ code: 'custom', message: 'Informe um raio válido', path: ['radius_meters'] })
      }
    }
    if (values.type === 'polygon' && (!values.geometry || values.geometry.length < 3)) {
      ctx.addIssue({
        code: 'custom',
        message: 'Marque pelo menos 3 pontos no mapa',
        path: ['geometry'],
      })
    }
  })

export type GeofenceFormValues = z.infer<typeof geofenceSchema>
