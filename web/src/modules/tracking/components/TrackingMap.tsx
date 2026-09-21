import { APIProvider, Map, AdvancedMarker, useMap } from '@vis.gl/react-google-maps'
import { useEffect, useMemo, useRef, type ReactNode } from 'react'
import { Crosshair, Locate } from 'lucide-react'
import type { Alert, Geofence, GpsPosition, Poi, TrackingLiveVehicle } from '@/shared/types/models'
import { cn } from '@/shared/utils/cn'
import { connectionStatus, hasActiveAlarm } from '../lib/tracking'
import { MapLayerToggles, MapLayersOverlay } from './MapLayers'

const DEFAULT_CENTER = { lat: -15.78, lng: -47.93 }
const DEFAULT_ZOOM = 4
const MAP_ID = 'dcf6e3453c5e5331850f50ef'

function toLatLng(point: { latitude: number; longitude: number }): google.maps.LatLngLiteral {
  return { lat: point.latitude, lng: point.longitude }
}

function CameraController({
  vehicles,
  selectedId,
  route,
  follow,
  fitRequest,
}: {
  vehicles: TrackingLiveVehicle[]
  selectedId: string | null
  route: GpsPosition[]
  follow: boolean
  fitRequest: { id: number; target: 'fleet' | 'vehicle' | 'route' } | null
}) {
  const map = useMap()
  const lastFitId = useRef<number | null>(null)
  const didInitialFit = useRef(false)

  useEffect(() => {
    if (!map) return

    const fit = (points: google.maps.LatLngLiteral[], padding = 72) => {
      if (points.length === 0) return
      if (points.length === 1) {
        map.setCenter(points[0])
        map.setZoom(16)
        return
      }
      const bounds = new google.maps.LatLngBounds()
      points.forEach((point) => bounds.extend(point))
      map.fitBounds(bounds, padding)
    }

    if (fitRequest && lastFitId.current !== fitRequest.id) {
      lastFitId.current = fitRequest.id
      if (fitRequest.target === 'route' && route.length > 0) {
        fit(route.map(toLatLng))
        return
      }
      if (fitRequest.target === 'vehicle') {
        const selected = vehicles.find((item) => item.id === selectedId && item.position)
        if (selected?.position) fit([toLatLng(selected.position)])
        return
      }
      const fleet = vehicles.filter((item) => item.position).map((item) => toLatLng(item.position!))
      fit(fleet.length > 0 ? fleet : [DEFAULT_CENTER])
      if (fleet.length === 0) map.setZoom(DEFAULT_ZOOM)
      return
    }

    if (!didInitialFit.current) {
      const fleet = vehicles.filter((item) => item.position).map((item) => toLatLng(item.position!))
      if (fleet.length === 0) return
      didInitialFit.current = true
      fit(fleet)
    }
  }, [map, vehicles, selectedId, route, fitRequest])

  useEffect(() => {
    if (!map || !follow) return
    const selected = vehicles.find((item) => item.id === selectedId && item.position)
    if (!selected?.position) return
    map.panTo(toLatLng(selected.position))
  }, [map, follow, selectedId, vehicles])

  return null
}

function RouteLayer({ route }: { route: GpsPosition[] }) {
  const map = useMap()

  useEffect(() => {
    if (!map || route.length < 2) return

    const polyline = new google.maps.Polyline({
      path: route.map(toLatLng),
      geodesic: true,
      strokeColor: '#0f766e',
      strokeOpacity: 1,
      strokeWeight: 5,
      zIndex: 2,
      map,
      icons: [
        {
          icon: {
            path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
            scale: 3,
            fillColor: '#0f766e',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 1,
          },
          offset: '40px',
          repeat: '72px',
        },
      ],
    })

    return () => polyline.setMap(null)
  }, [map, route])

  if (route.length === 0) return null

  const start = route[0]
  const end = route[route.length - 1]

  return (
    <>
      {start && (
        <AdvancedMarker position={toLatLng(start)} zIndex={5}>
          <div className="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white">Início</div>
        </AdvancedMarker>
      )}
      {end && end.id !== start?.id && (
        <AdvancedMarker position={toLatLng(end)} zIndex={5}>
          <div className="rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-semibold text-white">Fim</div>
        </AdvancedMarker>
      )}
    </>
  )
}

function VehicleMarker({
  vehicle,
  selected,
  onSelect,
}: {
  vehicle: TrackingLiveVehicle
  selected: boolean
  onSelect: (id: string) => void
}) {
  if (!vehicle.position) return null

  const status = connectionStatus(vehicle)
  const alarm = hasActiveAlarm(vehicle)
  const heading = vehicle.position.heading ?? 0

  return (
    <AdvancedMarker
      position={toLatLng(vehicle.position)}
      onClick={() => onSelect(vehicle.id)}
      title={vehicle.plate}
      zIndex={selected ? 4 : alarm ? 3 : 1}
    >
      <div className="flex flex-col items-center">
        <div
          className={cn(
            'flex size-7 items-center justify-center rounded-full border-2 border-white shadow',
            alarm && 'bg-danger',
            !alarm && status === 'online' && 'bg-primary',
            !alarm && status === 'offline' && 'bg-muted',
            !alarm && status === 'no_position' && 'bg-warning',
            selected && 'ring-2 ring-primary ring-offset-1 ring-offset-white',
            alarm && !selected && 'ring-2 ring-danger/40 ring-offset-1 ring-offset-white',
          )}
          style={{ transform: `rotate(${heading}deg)` }}
        >
          <span className="block h-2.5 w-1.5 rounded-sm bg-white" />
        </div>
        <span
          className={cn(
            'mt-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold shadow',
            selected ? 'bg-primary text-primary-foreground' : 'bg-surface text-foreground',
          )}
        >
          {vehicle.plate}
        </span>
      </div>
    </AdvancedMarker>
  )
}

export function TrackingMap({
  vehicles,
  selectedId,
  onSelect,
  route,
  playbackPosition,
  follow,
  fitRequest,
  onFitFleet,
  onFitSelected,
  showVehicles = true,
  showGeofences = true,
  showPois = true,
  showAlerts = true,
  onToggleVehicles,
  onToggleGeofences,
  onTogglePois,
  onToggleAlerts,
  geofences = [],
  pois = [],
  alerts = [],
  selectedGeofenceId = null,
  selectedPoiId = null,
  selectedAlertId = null,
  onSelectGeofence,
  onSelectPoi,
  onSelectAlert,
  layersAction,
}: {
  vehicles: TrackingLiveVehicle[]
  selectedId: string | null
  onSelect: (id: string) => void
  route: GpsPosition[]
  playbackPosition: GpsPosition | null
  follow: boolean
  fitRequest: { id: number; target: 'fleet' | 'vehicle' | 'route' } | null
  onFitFleet: () => void
  onFitSelected: () => void
  showVehicles?: boolean
  showGeofences?: boolean
  showPois?: boolean
  showAlerts?: boolean
  onToggleVehicles?: (value: boolean) => void
  onToggleGeofences?: (value: boolean) => void
  onTogglePois?: (value: boolean) => void
  onToggleAlerts?: (value: boolean) => void
  geofences?: Geofence[]
  pois?: Poi[]
  alerts?: Alert[]
  selectedGeofenceId?: string | null
  selectedPoiId?: string | null
  selectedAlertId?: string | null
  onSelectGeofence?: (id: string) => void
  onSelectPoi?: (id: string) => void
  onSelectAlert?: (id: string) => void
  layersAction?: ReactNode
}) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined

  const center = useMemo(() => {
    const selected = vehicles.find((item) => item.id === selectedId && item.position)
    if (selected?.position) return toLatLng(selected.position)
    const first = vehicles.find((item) => item.position)
    if (first?.position) return toLatLng(first.position)
    return DEFAULT_CENTER
  }, [vehicles, selectedId])

  if (!apiKey) {
    return (
      <div className="flex h-full items-center justify-center rounded-xl bg-surface-2 px-6 text-center text-sm text-muted">
        Defina <code className="mx-1">VITE_GOOGLE_MAPS_API_KEY</code> para exibir o mapa.
      </div>
    )
  }

  return (
    <div className="relative h-full min-h-[360px] overflow-hidden rounded-xl">
      <APIProvider apiKey={apiKey}>
        <Map
          className="h-full w-full"
          defaultCenter={center}
          defaultZoom={DEFAULT_ZOOM}
          mapId={MAP_ID}
          gestureHandling="greedy"
          disableDefaultUI={false}
          fullscreenControl
          mapTypeControl
          streetViewControl={false}
        >
          <CameraController
            vehicles={vehicles}
            selectedId={selectedId}
            route={route}
            follow={follow}
            fitRequest={fitRequest}
          />
          <RouteLayer route={route} />
          <MapLayersOverlay
            showGeofences={showGeofences}
            showPois={showPois}
            geofences={geofences}
            pois={pois}
            selectedGeofenceId={selectedGeofenceId}
            selectedPoiId={selectedPoiId}
            onSelectGeofence={onSelectGeofence ?? (() => undefined)}
            onSelectPoi={onSelectPoi ?? (() => undefined)}
          />
          {showAlerts &&
            alerts
              .filter((alert) => alert.latitude != null && alert.longitude != null)
              .map((alert) => (
                <AdvancedMarker
                  key={alert.id}
                  position={{ lat: alert.latitude!, lng: alert.longitude! }}
                  onClick={() => onSelectAlert?.(alert.id)}
                  zIndex={alert.id === selectedAlertId ? 5 : 2}
                  title={alert.title}
                >
                  <div
                    className={cn(
                      'rounded px-1.5 py-0.5 text-[10px] font-semibold text-white shadow',
                      alert.severity === 'critical' ? 'bg-rose-700' : 'bg-amber-600',
                      alert.id === selectedAlertId && 'ring-2 ring-white',
                    )}
                  >
                    {alert.type_label ?? alert.type}
                  </div>
                </AdvancedMarker>
              ))}
          {showVehicles &&
            vehicles.map((vehicle) => (
              <VehicleMarker
                key={vehicle.id}
                vehicle={vehicle}
                selected={vehicle.id === selectedId}
                onSelect={onSelect}
              />
            ))}
          {playbackPosition && (
            <AdvancedMarker position={toLatLng(playbackPosition)} zIndex={6}>
              <div className="size-4 rounded-full border-2 border-white bg-amber-500 shadow" />
            </AdvancedMarker>
          )}
        </Map>
      </APIProvider>

      {onToggleVehicles && onToggleGeofences && onTogglePois && (
        <MapLayerToggles
          showVehicles={showVehicles}
          showGeofences={showGeofences}
          showPois={showPois}
          showAlerts={showAlerts}
          onToggleVehicles={onToggleVehicles}
          onToggleGeofences={onToggleGeofences}
          onTogglePois={onTogglePois}
          onToggleAlerts={onToggleAlerts}
          action={layersAction}
        />
      )}

      {selectedAlertId && (
        <div className="absolute bottom-3 left-3 max-w-xs rounded-lg bg-surface/95 px-3 py-2 text-xs shadow-card">
          {(() => {
            const alert = alerts.find((item) => item.id === selectedAlertId)
            if (!alert) return null
            return (
              <div className="space-y-0.5">
                <p className="font-semibold text-foreground">{alert.title}</p>
                <p className="text-muted">
                  {alert.vehicle?.plate ?? '—'} ·{' '}
                  {alert.occurred_at ? new Date(alert.occurred_at).toLocaleString('pt-BR') : ''}
                </p>
                {alert.description && <p className="text-muted">{alert.description}</p>}
              </div>
            )
          })()}
        </div>
      )}

      <div className="absolute top-3 right-3 flex flex-col gap-2">
        <button
          type="button"
          onClick={onFitFleet}
          className="flex items-center gap-1.5 rounded-lg bg-surface px-2.5 py-1.5 text-xs font-medium text-foreground shadow-card"
          aria-label="Centralizar frota"
        >
          <Locate className="size-3.5" />
          Centralizar frota
        </button>
        <button
          type="button"
          onClick={onFitSelected}
          disabled={!selectedId}
          className="flex items-center gap-1.5 rounded-lg bg-surface px-2.5 py-1.5 text-xs font-medium text-foreground shadow-card disabled:opacity-50"
          aria-label="Centralizar veículo selecionado"
        >
          <Crosshair className="size-3.5" />
          Centralizar veículo
        </button>
      </div>
    </div>
  )
}
