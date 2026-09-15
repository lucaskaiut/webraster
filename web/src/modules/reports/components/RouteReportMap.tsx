import { useEffect } from 'react'
import { AdvancedMarker, APIProvider, Map, useMap } from '@vis.gl/react-google-maps'
import type { GpsPosition } from '@/shared/types/models'

const DEFAULT_CENTER = { lat: -15.78, lng: -47.93 }
const DEFAULT_ZOOM = 4
const MAP_ID = 'dcf6e3453c5e5331850f50ef'

function toLatLng(point: { latitude: number; longitude: number }): google.maps.LatLngLiteral {
  return { lat: point.latitude, lng: point.longitude }
}

function RouteOverlay({ route }: { route: GpsPosition[] }) {
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
    })

    return () => polyline.setMap(null)
  }, [map, route])

  useEffect(() => {
    if (!map || route.length === 0) return

    if (route.length === 1) {
      map.setCenter(toLatLng(route[0]))
      map.setZoom(16)
      return
    }

    const bounds = new google.maps.LatLngBounds()
    route.forEach((point) => bounds.extend(toLatLng(point)))
    map.fitBounds(bounds, 72)
  }, [map, route])

  if (route.length === 0) return null

  const start = route[0]
  const end = route[route.length - 1]

  return (
    <>
      <AdvancedMarker position={toLatLng(start)} zIndex={5}>
        <div className="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white">
          Início
        </div>
      </AdvancedMarker>
      {end.id !== start.id && (
        <AdvancedMarker position={toLatLng(end)} zIndex={5}>
          <div className="rounded-full bg-rose-600 px-2 py-0.5 text-[11px] font-semibold text-white">
            Fim
          </div>
        </AdvancedMarker>
      )}
    </>
  )
}

export function RouteReportMap({ route }: { route: GpsPosition[] }) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined

  if (!apiKey) {
    return (
      <div className="flex h-[420px] items-center justify-center rounded-xl bg-surface-2 px-6 text-center text-sm text-muted">
        Defina <code className="mx-1">VITE_GOOGLE_MAPS_API_KEY</code> para exibir o mapa.
      </div>
    )
  }

  return (
    <div className="h-[420px] overflow-hidden rounded-xl">
      <APIProvider apiKey={apiKey}>
        <Map
          className="h-full w-full"
          defaultCenter={DEFAULT_CENTER}
          defaultZoom={DEFAULT_ZOOM}
          mapId={MAP_ID}
          gestureHandling="greedy"
          fullscreenControl
        >
          <RouteOverlay route={route} />
        </Map>
      </APIProvider>
    </div>
  )
}
