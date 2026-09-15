import type { DeviceAlarm, GpsPosition, TrackingLiveVehicle } from '@/shared/types/models'
import { parseIsoDate, type DateRange } from '@/shared/utils/date'

export type FleetFilter =
  | 'all'
  | 'online'
  | 'offline'
  | 'moving'
  | 'stopped'
  | 'no_signal'
  | 'with_alarm'
export type ConnectionStatus = 'online' | 'offline' | 'no_position'
export type MotionStatus = 'moving' | 'stopped'

const MOVING_KMH = 2
const KNOTS_TO_KMH = 1.852

export function speedKmh(speed: number | null | undefined): number | null {
  if (speed === null || speed === undefined || Number.isNaN(speed)) return null

  return speed * KNOTS_TO_KMH
}

export function formatSpeed(speed: number | null | undefined): string {
  const kmh = speedKmh(speed)

  if (kmh === null) return 'Não informado'

  return `${kmh.toLocaleString('pt-BR', { maximumFractionDigits: 0 })} km/h`
}

export function formatHeading(heading: number | null | undefined): string {
  if (heading === null || heading === undefined || Number.isNaN(heading)) return 'Não informado'

  const directions = ['Norte', 'Nordeste', 'Leste', 'Sudeste', 'Sul', 'Sudoeste', 'Oeste', 'Noroeste']
  const index = Math.round((((heading % 360) + 360) % 360) / 45) % 8

  return directions[index] ?? 'Não informado'
}

export function formatCoordinates(lat: number | null | undefined, lng: number | null | undefined): string {
  if (lat === null || lat === undefined || lng === null || lng === undefined) return 'Indisponível'

  return `${lat.toFixed(6)}, ${lng.toFixed(6)}`
}

export function formatMeters(meters: number | null | undefined): string {
  if (meters === null || meters === undefined) return 'Não informado'

  if (meters >= 1000) {
    return `${(meters / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} km`
  }

  return `${Math.round(meters).toLocaleString('pt-BR')} m`
}

export function formatDuration(ms: number): string {
  const totalMinutes = Math.max(0, Math.round(ms / 60000))
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60

  if (hours === 0) return `${minutes}min`

  return `${hours}h ${String(minutes).padStart(2, '0')}min`
}

export function formatUpdatedAt(value: string | null | undefined, now = Date.now()): string {
  if (!value) return 'Sem sinal'

  const diff = Math.max(0, Math.round((now - new Date(value).getTime()) / 1000))

  if (diff < 5) return 'agora'
  if (diff < 60) return `há ${diff}s`
  if (diff < 3600) return `há ${Math.floor(diff / 60)} min`
  if (diff < 86400) return `há ${Math.floor(diff / 3600)} h`

  return `há ${Math.floor(diff / 86400)} d`
}

export function connectionStatus(vehicle: TrackingLiveVehicle): ConnectionStatus {
  if (!vehicle.position) return 'no_position'

  return vehicle.online ? 'online' : 'offline'
}

export function vehicleAlarms(vehicle: TrackingLiveVehicle): DeviceAlarm[] {
  return vehicle.position?.alarms ?? []
}

export function hasActiveAlarm(vehicle: TrackingLiveVehicle): boolean {
  return vehicleAlarms(vehicle).length > 0
}

export function motionStatus(vehicle: TrackingLiveVehicle): MotionStatus | null {
  if (!vehicle.position || !vehicle.online) return null

  if (vehicle.position.motion === true) return 'moving'
  if (vehicle.position.motion === false) return 'stopped'

  const kmh = speedKmh(vehicle.position.speed) ?? 0

  return kmh >= MOVING_KMH ? 'moving' : 'stopped'
}

export function matchesFleetFilter(vehicle: TrackingLiveVehicle, filter: FleetFilter): boolean {
  const connection = connectionStatus(vehicle)
  const motion = motionStatus(vehicle)

  switch (filter) {
    case 'online':
      return connection === 'online'
    case 'offline':
      return connection === 'offline'
    case 'no_signal':
      return connection === 'no_position'
    case 'moving':
      return motion === 'moving'
    case 'stopped':
      return motion === 'stopped'
    case 'with_alarm':
      return hasActiveAlarm(vehicle)
    default:
      return true
  }
}

export function matchesSearch(vehicle: TrackingLiveVehicle, search: string): boolean {
  const term = search.trim().toLowerCase()

  if (!term) return true

  const haystack = [
    vehicle.plate,
    vehicle.brand,
    vehicle.model,
    vehicle.client?.name,
    vehicle.equipment?.imei,
    vehicle.equipment?.model,
    vehicle.id,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()

  return haystack.includes(term.replace(/\s+/g, ' '))
}

export function fleetStats(vehicles: TrackingLiveVehicle[]) {
  return vehicles.reduce(
    (stats, vehicle) => {
      stats.total += 1
      const connection = connectionStatus(vehicle)
      const motion = motionStatus(vehicle)

      if (connection === 'online') stats.online += 1
      if (connection === 'offline') stats.offline += 1
      if (connection === 'no_position') stats.noSignal += 1
      if (motion === 'moving') stats.moving += 1
      if (motion === 'stopped') stats.stopped += 1
      if (hasActiveAlarm(vehicle)) stats.withAlarm += 1

      return stats
    },
    { total: 0, online: 0, offline: 0, moving: 0, stopped: 0, noSignal: 0, withAlarm: 0 },
  )
}

export function haversineMeters(
  from: Pick<GpsPosition, 'latitude' | 'longitude'>,
  to: Pick<GpsPosition, 'latitude' | 'longitude'>,
): number {
  const toRad = (value: number) => (value * Math.PI) / 180
  const earth = 6371000
  const dLat = toRad(to.latitude - from.latitude)
  const dLng = toRad(to.longitude - from.longitude)
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(from.latitude)) * Math.cos(toRad(to.latitude)) * Math.sin(dLng / 2) ** 2

  return 2 * earth * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

export interface RouteStats {
  distanceMeters: number
  durationMs: number
  maxSpeedKmh: number | null
  stops: number
}

export function summarizeRoute(route: GpsPosition[]): RouteStats {
  if (route.length === 0) {
    return { distanceMeters: 0, durationMs: 0, maxSpeedKmh: null, stops: 0 }
  }

  let distance = 0
  let maxSpeed = 0
  let stops = 0
  let stopped = false

  for (let index = 0; index < route.length; index += 1) {
    const point = route[index]!
    const kmh = speedKmh(point.speed) ?? 0
    maxSpeed = Math.max(maxSpeed, kmh)

    if (index > 0) {
      distance += haversineMeters(route[index - 1]!, point)
    }

    const isStopped = kmh < MOVING_KMH
    if (isStopped && !stopped) {
      stops += 1
      stopped = true
    } else if (!isStopped) {
      stopped = false
    }
  }

  const first = new Date(route[0]!.recorded_at).getTime()
  const last = new Date(route[route.length - 1]!.recorded_at).getTime()

  return {
    distanceMeters: distance,
    durationMs: Math.max(0, last - first),
    maxSpeedKmh: maxSpeed,
    stops,
  }
}

export interface DerivedEvent {
  id: string
  at: string
  label: string
  tone: 'neutral' | 'success' | 'warning'
}

export function deriveRouteEvents(route: GpsPosition[]): DerivedEvent[] {
  const events: DerivedEvent[] = []

  for (let index = 0; index < route.length; index += 1) {
    const point = route[index]!
    const previous = index > 0 ? route[index - 1] : null

    if (point.ignition !== null && point.ignition !== previous?.ignition) {
      events.push({
        id: `${point.id}-ignition`,
        at: point.recorded_at,
        label: point.ignition ? 'Ignição ligada' : 'Ignição desligada',
        tone: point.ignition ? 'success' : 'neutral',
      })
    }

    const moving = (speedKmh(point.speed) ?? 0) >= MOVING_KMH
    const wasMoving = previous ? (speedKmh(previous.speed) ?? 0) >= MOVING_KMH : false

    if (previous && moving !== wasMoving) {
      events.push({
        id: `${point.id}-motion`,
        at: point.recorded_at,
        label: moving ? 'Veículo em movimento' : 'Veículo parado',
        tone: moving ? 'success' : 'neutral',
      })
    }

    const previousAlarms = new Set((previous?.alarms ?? []).map((alarm) => alarm.code))
    for (const alarm of point.alarms ?? []) {
      if (previousAlarms.has(alarm.code)) continue

      events.push({
        id: `${point.id}-alarm-${alarm.code}`,
        at: point.recorded_at,
        label: alarm.label,
        tone: alarm.severity === 'critical' || alarm.severity === 'high' ? 'warning' : 'neutral',
      })
    }

    if (previous) {
      const gap = new Date(point.recorded_at).getTime() - new Date(previous.recorded_at).getTime()
      if (gap > 15 * 60 * 1000) {
        events.push({
          id: `${point.id}-gap`,
          at: previous.recorded_at,
          label: 'Perda de comunicação',
          tone: 'warning',
        })
        events.push({
          id: `${point.id}-recover`,
          at: point.recorded_at,
          label: 'Comunicação restabelecida',
          tone: 'success',
        })
      }
    }
  }

  return events
}

export function vehicleLabel(vehicle: TrackingLiveVehicle): string {
  return [vehicle.brand, vehicle.model].filter(Boolean).join(' ') || 'Veículo'
}

/**
 * Converte um DateRange (datas em YYYY-MM-DD) para o intervalo completo
 * do dia em ISO, no formato esperado pela API de histórico.
 */
export function rangeToApiBounds(range: DateRange): { from: string; to: string } | null {
  const start = parseIsoDate(range.from)
  const end = parseIsoDate(range.to)

  if (!start || !end || start > end) {
    return null
  }

  start.setHours(0, 0, 0, 0)
  end.setHours(23, 59, 59, 999)

  return { from: start.toISOString(), to: end.toISOString() }
}
