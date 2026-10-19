import { useEffect } from 'react'
import { AdvancedMarker, APIProvider, Map, useMap } from '@vis.gl/react-google-maps'
import type { ScheduleRoute } from '../services/service-orders.service'

const MAP_ID = 'dcf6e3453c5e5331850f50ef'
const DEFAULT_CENTER = { lat: -15.78, lng: -47.93 }
const DEFAULT_ZOOM = 4

function toLatLng([latitude, longitude]: [number, number]): google.maps.LatLngLiteral {
  return { lat: latitude, lng: longitude }
}

function RouteOverlay({ route }: { route: ScheduleRoute }) {
  const map = useMap()

  useEffect(() => {
    if (!map) return

    const lines: google.maps.Polyline[] = []

    if (route.outbound.length > 1) {
      lines.push(
        new google.maps.Polyline({
          path: route.outbound.map(toLatLng),
          geodesic: true,
          strokeColor: '#2563eb',
          strokeOpacity: 0.95,
          strokeWeight: 4,
          zIndex: 2,
          map,
        }),
      )
    }

    if (route.return.length > 1) {
      lines.push(
        new google.maps.Polyline({
          path: route.return.map(toLatLng),
          geodesic: true,
          strokeColor: '#94a3b8',
          strokeOpacity: 0.75,
          strokeWeight: 9,
          zIndex: 1,
          map,
        }),
      )
    }

    return () => lines.forEach((line) => line.setMap(null))
  }, [map, route])

  useEffect(() => {
    if (!map) return

    const points = [
      ...(route.origin ? [toLatLng([route.origin.latitude, route.origin.longitude])] : []),
      ...(route.destination
        ? [toLatLng([route.destination.latitude, route.destination.longitude])]
        : []),
      ...(route.return_to
        ? [toLatLng([route.return_to.latitude, route.return_to.longitude])]
        : []),
      ...route.outbound.map(toLatLng),
      ...route.return.map(toLatLng),
    ]

    if (points.length === 0) return

    if (points.length === 1) {
      map.setCenter(points[0])
      map.setZoom(16)
      return
    }

    const bounds = new google.maps.LatLngBounds()
    points.forEach((point) => bounds.extend(point))
    map.fitBounds(bounds, 64)
  }, [map, route])

  return (
    <>
      {route.origin && (
        <AdvancedMarker
          position={toLatLng([route.origin.latitude, route.origin.longitude])}
          zIndex={5}>
          <div className="rounded-full bg-slate-700 px-2 py-0.5 text-[11px] font-semibold text-white">
            {route.origin.kind === 'order' ? 'OS anterior' : 'Base'}
          </div>
        </AdvancedMarker>
      )}
      {route.destination && (
        <AdvancedMarker
          position={toLatLng([route.destination.latitude, route.destination.longitude])}
          zIndex={5}>
          <div className="rounded-full bg-blue-600 px-2 py-0.5 text-[11px] font-semibold text-white">
            Atendimento
          </div>
        </AdvancedMarker>
      )}
      {route.return_to && (
        <AdvancedMarker
          position={toLatLng([route.return_to.latitude, route.return_to.longitude])}
          zIndex={4}>
          <div className="rounded-full bg-slate-500 px-2 py-0.5 text-[11px] font-semibold text-white">
            {route.return_to.kind === 'order' ? 'Próxima OS' : 'Base'}
          </div>
        </AdvancedMarker>
      )}
    </>
  )
}

export function ServiceOrderRouteMap({ route }: { route: ScheduleRoute }) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined

  if (!apiKey) {
    return (
      <div className="flex h-[332px] items-center justify-center rounded-xl bg-surface-2 px-6 text-center text-sm text-muted">
        Defina <code className="mx-1">VITE_GOOGLE_MAPS_API_KEY</code> para exibir o mapa.
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center gap-4 text-xs text-muted">
        <span className="flex items-center gap-1.5">
          <span className="h-0.5 w-5 rounded-full bg-blue-600" /> Ida
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-0.5 w-5 rounded-full bg-slate-400" /> Volta
        </span>
        <span>A volta segue até a próxima OS ou para a base.</span>
      </div>
      <div className="h-[332px] overflow-hidden rounded-xl">
        <APIProvider apiKey={apiKey}>
          <Map
            className="h-full w-full"
            defaultCenter={DEFAULT_CENTER}
            defaultZoom={DEFAULT_ZOOM}
            mapId={MAP_ID}
            gestureHandling="greedy"
          >
            <RouteOverlay route={route} />
          </Map>
        </APIProvider>
      </div>
    </div>
  )
}
