import { useCallback, useState } from 'react'
import type { MarkerInfoOptions } from '../components/TrackingMap'

const STORAGE_KEY = 'webraster-map-marker-info'

export const DEFAULT_MARKER_INFO: MarkerInfoOptions = {
  plate: true,
  vehicle: false,
  driver: false,
}

function readStored(): MarkerInfoOptions {
  if (typeof window === 'undefined') return DEFAULT_MARKER_INFO

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (!raw) return DEFAULT_MARKER_INFO

    const parsed = JSON.parse(raw) as Partial<MarkerInfoOptions>

    return {
      plate: parsed.plate ?? DEFAULT_MARKER_INFO.plate,
      vehicle: parsed.vehicle ?? DEFAULT_MARKER_INFO.vehicle,
      driver: parsed.driver ?? DEFAULT_MARKER_INFO.driver,
    }
  } catch {
    return DEFAULT_MARKER_INFO
  }
}

/** Preferências (persistidas) das informações exibidas junto aos veículos no mapa. */
export function useMapMarkerInfo() {
  const [markerInfo, setMarkerInfo] = useState<MarkerInfoOptions>(readStored)

  const toggleMarkerInfo = useCallback((key: keyof MarkerInfoOptions, value: boolean) => {
    setMarkerInfo((current) => {
      const next = { ...current, [key]: value }

      try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next))
      } catch {
        // Ignora falhas de armazenamento (modo privado, cota etc.).
      }

      return next
    })
  }, [])

  return { markerInfo, toggleMarkerInfo }
}
