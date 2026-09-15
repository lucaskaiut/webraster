import { useEffect } from 'react'
import { AdvancedMarker, APIProvider, Map, useMap } from '@vis.gl/react-google-maps'
import type { TripItem, TripPoint } from '@/shared/types/models'

const DEFAULT_CENTER = { lat: -15.78, lng: -47.93 }
const DEFAULT_ZOOM = 4
const MAP_ID = 'dcf6e3453c5e5331850f50ef'

function toLatLng(point: { latitude: number; longitude: number }): google.maps.LatLngLiteral {
  return { lat: point.latitude, lng: point.longitude }
}

/** Reduz os marcadores numerados para no máximo `limit`, mantendo a ordem. */
function sampleIntermediate(points: TripPoint[], limit: number): TripPoint[] {
  if (points.length <= limit) return points

  const step = (points.length - 1) / (limit - 1)
  const sampled: TripPoint[] = []

  for (let i = 0; i < limit; i += 1) {
    sampled.push(points[Math.round(i * step)])
  }

  return sampled
}

function TripOverlay({ trip }: { trip: TripItem | null }) {
  const map = useMap()

  useEffect(() => {
    if (!map || !trip || trip.points.length < 2) return

    const polyline = new google.maps.Polyline({
      path: trip.points.map(toLatLng),
      geodesic: true,
      strokeColor: '#0f766e',
      strokeOpacity: 1,
      strokeWeight: 5,
      zIndex: 2,
      map,
    })

    return () => polyline.setMap(null)
  }, [map, trip])

  useEffect(() => {
    if (!map || !trip || trip.points.length === 0) return

    const points = trip.points.map(toLatLng)

    if (points.length === 1) {
      map.setCenter(points[0])
      map.setZoom(16)
      return
    }

    const bounds = new google.maps.LatLngBounds()
    points.forEach((point) => bounds.extend(point))
    map.fitBounds(bounds, 72)
  }, [map, trip])

  if (!trip || trip.points.length === 0) return null

  const origin = trip.points[0]
  const destination = trip.points[trip.points.length - 1]
  const intermediate = sampleIntermediate(trip.points.slice(1, -1), 25)

  return (
    <>
      <AdvancedMarker position={toLatLng(origin)} zIndex={6}>
        <div className="flex size-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white shadow">
          Início
        </div>
      </AdvancedMarker>

      {trip.points.length > 1 && (
        <AdvancedMarker position={toLatLng(destination)} zIndex={6}>
          <div className="flex size-7 items-center justify-center rounded-full bg-rose-600 text-xs font-bold text-white shadow">
            Fim
          </div>
        </AdvancedMarker>
      )}

      {intermediate.map((point, index) => (
        <AdvancedMarker key={`${point.recorded_at ?? ''}-${index}`} position={toLatLng(point)} zIndex={4}>
          <div className="flex size-5 items-center justify-center rounded-full border border-white bg-surface text-[10px] font-semibold text-foreground shadow">
            {index + 1}
          </div>
        </AdvancedMarker>
      ))}
    </>
  )
}

export function TripsReportMap({ trip }: { trip: TripItem | null }) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined

  if (!apiKey) {
    return (
      <div className="flex h-full min-h-[420px] items-center justify-center rounded-xl bg-surface-2 px-6 text-center text-sm text-muted">
        Defina <code className="mx-1">VITE_GOOGLE_MAPS_API_KEY</code> para exibir o mapa.
      </div>
    )
  }

  return (
    <div className="h-full min-h-[420px] overflow-hidden rounded-xl">
      <APIProvider apiKey={apiKey}>
        <Map
          className="h-full w-full"
          defaultCenter={DEFAULT_CENTER}
          defaultZoom={DEFAULT_ZOOM}
          mapId={MAP_ID}
          gestureHandling="greedy"
          fullscreenControl
        >
          <TripOverlay trip={trip} />
        </Map>
      </APIProvider>
    </div>
  )
}
