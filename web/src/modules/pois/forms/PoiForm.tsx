import { useCallback, useEffect } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { APIProvider, Map, AdvancedMarker, useMap } from '@vis.gl/react-google-maps'
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
import { usePoiCategoriesQuery } from '../hooks/usePois'
import type { PoiPayload } from '../services/pois.service'
import { poiSchema, type PoiFormValues } from '../schemas/poi.schema'

const MAP_ID = 'dcf6e3453c5e5331850f50ef'
const DEFAULT_CENTER = { lat: -25.4284, lng: -49.2733 }

interface PoiFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<PoiFormValues>
  submitting: boolean
  onSubmit: (payload: PoiPayload) => Promise<unknown>
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

function ClickPicker({ onPick }: { onPick: (point: { lat: number; lng: number }) => void }) {
  const map = useMap()

  useEffect(() => {
    if (!map) return
    const listener = map.addListener('click', (event: google.maps.MapMouseEvent) => {
      if (!event.latLng) return
      onPick({ lat: event.latLng.lat(), lng: event.latLng.lng() })
    })
    return () => listener.remove()
  }, [map, onPick])

  return null
}

export function PoiForm({ mode, defaultValues, submitting, onSubmit }: PoiFormProps) {
  const categoriesQuery = usePoiCategoriesQuery()
  const form = useForm<PoiFormValues>({
    resolver: zodResolver(poiSchema),
    defaultValues: {
      client_id: '',
      poi_category_id: '',
      name: '',
      description: '',
      latitude: undefined as unknown as number,
      longitude: undefined as unknown as number,
      address: '',
      is_active: true,
      ...defaultValues,
    },
  })

  const latitude = useWatch({ control: form.control, name: 'latitude' })
  const longitude = useWatch({ control: form.control, name: 'longitude' })
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined

  const handlePick = useCallback(
    (point: { lat: number; lng: number }) => {
      form.setValue('latitude', point.lat, { shouldValidate: true })
      form.setValue('longitude', point.lng, { shouldValidate: true })
    },
    [form],
  )

  const handleSubmit = async (values: PoiFormValues) => {
    const payload: PoiPayload = {
      client_id: values.client_id,
      poi_category_id: values.poi_category_id,
      name: values.name,
      description: values.description || null,
      latitude: values.latitude,
      longitude: values.longitude,
      address: values.address || null,
      is_active: values.is_active,
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const categoryOptions = (categoriesQuery.data ?? []).map((category) => ({
    value: category.id,
    label: category.name,
  }))

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
                <SelectField
                  name="poi_category_id"
                  label="Categoria"
                  required
                  className="sm:col-span-2"
                  placeholder="Selecione"
                  options={categoryOptions}
                />
                <TextField name="name" label="Nome" required placeholder="Oficina Central" className="sm:col-span-2" />
                <TextareaField name="description" label="Descrição" placeholder="Opcional" className="sm:col-span-2" />
                <TextField name="address" label="Endereço" placeholder="Opcional" className="sm:col-span-2" />
                <SwitchField name="is_active" label="POI ativo" className="sm:col-span-2" />
              </div>
            </Section>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <ButtonLink to="/pois" variant="secondary">
                Cancelar
              </ButtonLink>
              <Button type="submit" loading={submitting}>
                {mode === 'create' ? 'Criar POI' : 'Salvar alterações'}
              </Button>
            </div>
          </Form>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="space-y-3">
          <Section title="Localização" description="Clique no mapa para posicionar o POI.">
            {apiKey ? (
              <div className="h-72 overflow-hidden rounded-xl bg-surface-2">
                <APIProvider apiKey={apiKey}>
                  <Map
                    className="h-full w-full"
                    defaultCenter={
                      latitude != null && longitude != null
                        ? { lat: latitude, lng: longitude }
                        : DEFAULT_CENTER
                    }
                    defaultZoom={13}
                    mapId={MAP_ID}
                    gestureHandling="greedy"
                    disableDefaultUI={false}
                    mapTypeControl={false}
                    streetViewControl={false}
                  >
                    <ClickPicker onPick={handlePick} />
                    {latitude != null && longitude != null && (
                      <AdvancedMarker position={{ lat: latitude, lng: longitude }} />
                    )}
                  </Map>
                </APIProvider>
              </div>
            ) : (
              <div className="flex h-72 items-center justify-center rounded-xl bg-surface-2 text-sm text-muted">
                Defina <code className="mx-1">VITE_GOOGLE_MAPS_API_KEY</code>
              </div>
            )}
            {latitude != null && longitude != null && (
              <p className="text-[13px] text-muted">
                {latitude.toFixed(6)}, {longitude.toFixed(6)}
              </p>
            )}
          </Section>
        </CardContent>
      </Card>
    </div>
  )
}
