import { useEffect, useMemo } from 'react'
import { useMap, AdvancedMarker } from '@vis.gl/react-google-maps'
import { Checkbox } from '@/shared/design-system'
import type { Geofence, Poi } from '@/shared/types/models'
import { cn } from '@/shared/utils/cn'

function GeofenceShapes({
  geofences,
  selectedId,
  onSelect,
}: {
  geofences: Geofence[]
  selectedId: string | null
  onSelect: (id: string) => void
}) {
  const map = useMap()

  useEffect(() => {
    if (!map) return

    const overlays: Array<google.maps.Circle | google.maps.Polygon> = []
    const listeners: google.maps.MapsEventListener[] = []

    for (const geofence of geofences) {
      const selected = geofence.id === selectedId
      const color = geofence.type === 'circle' ? '#0f766e' : '#1d4ed8'

      if (geofence.type === 'circle' && geofence.center_latitude != null && geofence.center_longitude != null) {
        const circle = new google.maps.Circle({
          map,
          center: { lat: geofence.center_latitude, lng: geofence.center_longitude },
          radius: geofence.radius_meters ?? 0,
          fillColor: color,
          fillOpacity: selected ? 0.28 : 0.14,
          strokeColor: color,
          strokeOpacity: selected ? 1 : 0.75,
          strokeWeight: selected ? 3 : 2,
          clickable: true,
        })
        listeners.push(circle.addListener('click', () => onSelect(geofence.id)))
        overlays.push(circle)
        continue
      }

      if (geofence.type === 'polygon' && geofence.geometry && geofence.geometry.length >= 3) {
        const polygon = new google.maps.Polygon({
          map,
          paths: geofence.geometry.map((point) => ({
            lat: point.latitude,
            lng: point.longitude,
          })),
          fillColor: color,
          fillOpacity: selected ? 0.28 : 0.14,
          strokeColor: color,
          strokeOpacity: selected ? 1 : 0.75,
          strokeWeight: selected ? 3 : 2,
          clickable: true,
        })
        listeners.push(polygon.addListener('click', () => onSelect(geofence.id)))
        overlays.push(polygon)
      }
    }

    return () => {
      listeners.forEach((listener) => listener.remove())
      overlays.forEach((overlay) => overlay.setMap(null))
    }
  }, [map, geofences, selectedId, onSelect])

  return null
}

export function MapLayersOverlay({
  showGeofences,
  showPois,
  geofences,
  pois,
  selectedGeofenceId,
  selectedPoiId,
  onSelectGeofence,
  onSelectPoi,
}: {
  showGeofences: boolean
  showPois: boolean
  geofences: Geofence[]
  pois: Poi[]
  selectedGeofenceId: string | null
  selectedPoiId: string | null
  onSelectGeofence: (id: string) => void
  onSelectPoi: (id: string) => void
}) {
  const selectedGeofence = useMemo(
    () => geofences.find((item) => item.id === selectedGeofenceId) ?? null,
    [geofences, selectedGeofenceId],
  )
  const selectedPoi = useMemo(
    () => pois.find((item) => item.id === selectedPoiId) ?? null,
    [pois, selectedPoiId],
  )

  return (
    <>
      {showGeofences && (
        <GeofenceShapes
          geofences={geofences}
          selectedId={selectedGeofenceId}
          onSelect={onSelectGeofence}
        />
      )}
      {showPois &&
        pois.map((poi) => (
          <AdvancedMarker
            key={poi.id}
            position={{ lat: poi.latitude, lng: poi.longitude }}
            onClick={() => onSelectPoi(poi.id)}
            zIndex={poi.id === selectedPoiId ? 3 : 1}
            title={poi.name}
          >
            <div
              className={cn(
                'rounded-full border-2 border-white px-2 py-0.5 text-[10px] font-semibold shadow',
                poi.id === selectedPoiId ? 'bg-amber-600 text-white' : 'bg-surface text-foreground',
              )}
              style={{ backgroundColor: poi.id === selectedPoiId ? undefined : poi.category?.color ?? undefined }}
            >
              {poi.name}
            </div>
          </AdvancedMarker>
        ))}

      {(selectedGeofence || selectedPoi) && (
        <div className="pointer-events-none absolute bottom-3 left-3 max-w-xs rounded-lg bg-surface/95 px-3 py-2 text-xs shadow-card">
          {selectedGeofence && (
            <div className="space-y-0.5">
              <p className="font-semibold text-foreground">{selectedGeofence.name}</p>
              <p className="text-muted">
                {selectedGeofence.type === 'circle' ? 'Círculo' : 'Polígono'} ·{' '}
                {selectedGeofence.is_active ? 'Ativa' : 'Inativa'}
              </p>
            </div>
          )}
          {selectedPoi && (
            <div className="space-y-0.5">
              <p className="font-semibold text-foreground">{selectedPoi.name}</p>
              <p className="text-muted">
                {selectedPoi.category?.name ?? 'Sem categoria'} · {selectedPoi.client?.name ?? '—'}
              </p>
              <p className="text-muted">
                {selectedPoi.latitude.toFixed(5)}, {selectedPoi.longitude.toFixed(5)}
              </p>
            </div>
          )}
        </div>
      )}
    </>
  )
}

export function MapLayerToggles({
  showVehicles,
  showGeofences,
  showPois,
  showAlerts,
  onToggleVehicles,
  onToggleGeofences,
  onTogglePois,
  onToggleAlerts,
}: {
  showVehicles: boolean
  showGeofences: boolean
  showPois: boolean
  showAlerts?: boolean
  onToggleVehicles: (value: boolean) => void
  onToggleGeofences: (value: boolean) => void
  onTogglePois: (value: boolean) => void
  onToggleAlerts?: (value: boolean) => void
}) {
  return (
    <div className="absolute top-3 left-3 flex flex-col gap-2 rounded-xl bg-surface/95 p-3 shadow-card">
      <Checkbox
        label="Veículos"
        checked={showVehicles}
        onChange={(event) => onToggleVehicles(event.target.checked)}
      />
      <Checkbox
        label="Geocercas"
        checked={showGeofences}
        onChange={(event) => onToggleGeofences(event.target.checked)}
      />
      <Checkbox
        label="POIs"
        checked={showPois}
        onChange={(event) => onTogglePois(event.target.checked)}
      />
      {onToggleAlerts && (
        <Checkbox
          label="Alertas"
          checked={!!showAlerts}
          onChange={(event) => onToggleAlerts(event.target.checked)}
        />
      )}
    </div>
  )
}
