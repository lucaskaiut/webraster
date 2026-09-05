import { APIProvider, Map, useMap, useMapsLibrary } from '@vis.gl/react-google-maps'
import { useEffect, useRef } from 'react'
import type { GeofencePoint } from '@/shared/types/models'

const MAP_ID = 'dcf6e3453c5e5331850f50ef'
const DEFAULT_CENTER = { lat: -25.4284, lng: -49.2733 }

function DrawingLayer({
  mode,
  center,
  radiusMeters,
  polygon,
  onCircleChange,
  onPolygonChange,
}: {
  mode: 'circle' | 'polygon'
  center: { lat: number; lng: number } | null
  radiusMeters: number
  polygon: GeofencePoint[]
  onCircleChange: (center: { lat: number; lng: number }, radius: number) => void
  onPolygonChange: (points: GeofencePoint[]) => void
}) {
  const map = useMap()
  const maps = useMapsLibrary('maps')
  const circleRef = useRef<google.maps.Circle | null>(null)
  const polygonRef = useRef<google.maps.Polygon | null>(null)
  const clickListener = useRef<google.maps.MapsEventListener | null>(null)

  useEffect(() => {
    if (!map || !maps) return

    clickListener.current?.remove()
    clickListener.current = map.addListener('click', (event: google.maps.MapMouseEvent) => {
      if (!event.latLng) return
      const lat = event.latLng.lat()
      const lng = event.latLng.lng()

      if (mode === 'circle') {
        onCircleChange({ lat, lng }, radiusMeters)
        return
      }

      onPolygonChange([...polygon, { latitude: lat, longitude: lng }])
    })

    return () => clickListener.current?.remove()
  }, [map, maps, mode, onCircleChange, onPolygonChange, polygon, radiusMeters])

  useEffect(() => {
    if (!map || !maps) return

    circleRef.current?.setMap(null)
    polygonRef.current?.setMap(null)
    circleRef.current = null
    polygonRef.current = null

    if (mode === 'circle' && center) {
      const circle = new google.maps.Circle({
        map,
        center,
        radius: radiusMeters,
        fillColor: '#0f766e',
        fillOpacity: 0.18,
        strokeColor: '#0f766e',
        strokeOpacity: 0.9,
        strokeWeight: 2,
        editable: true,
        draggable: true,
      })

      const sync = () => {
        const nextCenter = circle.getCenter()
        if (!nextCenter) return
        onCircleChange(
          { lat: nextCenter.lat(), lng: nextCenter.lng() },
          Math.max(1, Math.round(circle.getRadius())),
        )
      }

      circle.addListener('center_changed', sync)
      circle.addListener('radius_changed', sync)
      circleRef.current = circle
      return
    }

    if (mode === 'polygon' && polygon.length > 0) {
      const path = polygon.map((point) => ({ lat: point.latitude, lng: point.longitude }))
      const shape = new google.maps.Polygon({
        map,
        paths: path,
        fillColor: '#1d4ed8',
        fillOpacity: 0.16,
        strokeColor: '#1d4ed8',
        strokeOpacity: 0.9,
        strokeWeight: 2,
        editable: polygon.length >= 3,
        draggable: false,
      })

      const sync = () => {
        const next = shape
          .getPath()
          .getArray()
          .map((point) => ({ latitude: point.lat(), longitude: point.lng() }))
        onPolygonChange(next)
      }

      shape.getPath().addListener('set_at', sync)
      shape.getPath().addListener('insert_at', sync)
      shape.getPath().addListener('remove_at', sync)
      polygonRef.current = shape
    }
  }, [map, maps, mode, center, radiusMeters, polygon, onCircleChange, onPolygonChange])

  useEffect(() => {
    return () => {
      circleRef.current?.setMap(null)
      polygonRef.current?.setMap(null)
    }
  }, [])

  return null
}

export function GeofenceDrawMap({
  mode,
  center,
  radiusMeters,
  polygon,
  onCircleChange,
  onPolygonChange,
}: {
  mode: 'circle' | 'polygon'
  center: { lat: number; lng: number } | null
  radiusMeters: number
  polygon: GeofencePoint[]
  onCircleChange: (center: { lat: number; lng: number }, radius: number) => void
  onPolygonChange: (points: GeofencePoint[]) => void
}) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined

  if (!apiKey) {
    return (
      <div className="flex h-72 items-center justify-center rounded-xl bg-surface-2 px-4 text-sm text-muted">
        Defina VITE_GOOGLE_MAPS_API_KEY para desenhar no mapa.
      </div>
    )
  }

  return (
    <div className="h-72 overflow-hidden rounded-xl bg-surface-2">
      <APIProvider apiKey={apiKey}>
        <Map
          className="h-full w-full"
          defaultCenter={center ?? DEFAULT_CENTER}
          defaultZoom={center ? 14 : 12}
          mapId={MAP_ID}
          gestureHandling="greedy"
          disableDefaultUI={false}
          mapTypeControl={false}
          streetViewControl={false}
        >
          <DrawingLayer
            mode={mode}
            center={center}
            radiusMeters={radiusMeters}
            polygon={polygon}
            onCircleChange={onCircleChange}
            onPolygonChange={onPolygonChange}
          />
        </Map>
      </APIProvider>
    </div>
  )
}
