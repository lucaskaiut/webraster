import { useCallback } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  Section,
  SelectField,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { clientsService } from '@/modules/clients/services/clients.service'
import type { GeofencePayload } from '../services/geofences.service'
import { geofenceSchema, type GeofenceFormValues } from '../schemas/geofence.schema'
import { GeofenceDrawMap } from '../components/GeofenceDrawMap'

interface GeofenceFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<GeofenceFormValues>
  submitting: boolean
  onSubmit: (payload: GeofencePayload) => Promise<unknown>
}

async function loadClientOptions(search: string) {
  const response = await clientsService.list({ search: search || undefined, per_page: 20 })
  return response.data.map((client) => ({ value: client.id, label: client.name }))
}

async function resolveClientLabel(value: string) {
  try {
    const client = await clientsService.get(value)
    return { value: client.id, label: client.name }
  } catch {
    return null
  }
}

export function GeofenceForm({ mode, defaultValues, submitting, onSubmit }: GeofenceFormProps) {
  const form = useForm<GeofenceFormValues>({
    resolver: zodResolver(geofenceSchema),
    defaultValues: {
      client_id: '',
      name: '',
      description: '',
      type: 'circle',
      is_active: true,
      center_latitude: null,
      center_longitude: null,
      radius_meters: 500,
      geometry: [],
      ...defaultValues,
    },
  })

  const type = useWatch({ control: form.control, name: 'type' })
  const radius = useWatch({ control: form.control, name: 'radius_meters' }) ?? 500
  const centerLat = useWatch({ control: form.control, name: 'center_latitude' })
  const centerLng = useWatch({ control: form.control, name: 'center_longitude' })
  const geometry = useWatch({ control: form.control, name: 'geometry' }) ?? []

  const handleCircleChange = useCallback(
    (center: { lat: number; lng: number }, nextRadius: number) => {
      form.setValue('center_latitude', center.lat, { shouldValidate: true })
      form.setValue('center_longitude', center.lng, { shouldValidate: true })
      form.setValue('radius_meters', nextRadius, { shouldValidate: true })
    },
    [form],
  )

  const handlePolygonChange = useCallback(
    (points: Array<{ latitude: number; longitude: number }>) => {
      form.setValue('geometry', points, { shouldValidate: true })
    },
    [form],
  )

  const handleSubmit = async (values: GeofenceFormValues) => {
    const payload: GeofencePayload = {
      client_id: values.client_id,
      name: values.name,
      description: values.description || null,
      type: values.type,
      is_active: values.is_active,
      center_latitude: values.type === 'circle' ? values.center_latitude : null,
      center_longitude: values.type === 'circle' ? values.center_longitude : null,
      radius_meters: values.type === 'circle' ? values.radius_meters : null,
      geometry: values.type === 'polygon' ? values.geometry : null,
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
      <Card>
        <CardContent>
          <Form form={form} onSubmit={handleSubmit} className="space-y-8">
            <Section title="Dados">
              <div className="grid gap-4 sm:grid-cols-2">
                <SearchSelectField
                  name="client_id"
                  label="Cliente"
                  required
                  className="sm:col-span-2"
                  placeholder="Buscar cliente..."
                  emptyMessage="Nenhum cliente encontrado"
                  loadOptions={loadClientOptions}
                  resolveLabel={resolveClientLabel}
                />
                <TextField name="name" label="Nome" required placeholder="Base operacional" className="sm:col-span-2" />
                <TextareaField name="description" label="Descrição" placeholder="Opcional" className="sm:col-span-2" />
                <SelectField
                  name="type"
                  label="Tipo"
                  required
                  options={[
                    { value: 'circle', label: 'Círculo' },
                    { value: 'polygon', label: 'Polígono' },
                  ]}
                />
                {type === 'circle' && (
                  <TextField name="radius_meters" label="Raio (metros)" type="number" />
                )}
                <SwitchField name="is_active" label="Geocerca ativa" className="sm:col-span-2" />
              </div>
            </Section>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <ButtonLink to="/geofences" variant="secondary">
                Cancelar
              </ButtonLink>
              {type === 'polygon' && (
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => form.setValue('geometry', [], { shouldValidate: true })}
                >
                  Limpar pontos
                </Button>
              )}
              <Button type="submit" loading={submitting}>
                {mode === 'create' ? 'Criar geocerca' : 'Salvar alterações'}
              </Button>
            </div>
          </Form>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-3">
          <Section
            title="Mapa"
            description={
              type === 'circle'
                ? 'Clique no mapa para definir o centro. Arraste o círculo para ajustar.'
                : 'Clique para adicionar vértices. Com 3 ou mais pontos, arraste as arestas.'
            }
          >
            <GeofenceDrawMap
              mode={type}
              center={
                centerLat != null && centerLng != null
                  ? { lat: centerLat, lng: centerLng }
                  : null
              }
              radiusMeters={Number(radius) || 500}
              polygon={geometry}
              onCircleChange={handleCircleChange}
              onPolygonChange={handlePolygonChange}
            />
            {type === 'polygon' && (
              <p className="text-[13px] text-muted">{geometry.length} ponto(s) marcados</p>
            )}
          </Section>
        </CardContent>
      </Card>
    </div>
  )
}
