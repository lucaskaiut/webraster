import type { TrackingLiveVehicle } from '@/shared/types/models'
import { vehicleAlarms } from './tracking'

export type IndicatorTone = 'success' | 'warning' | 'danger' | 'muted'
export type ConnectionIndicator = 'online' | 'delayed' | 'offline' | 'unknown'
export type ExternalPowerStatus = 'connected' | 'disconnected' | 'unknown'
export type GpsStatus = 'valid' | 'invalid' | 'unknown'
export type SignalLevel = 'strong' | 'medium' | 'weak' | 'none' | 'unknown'
export type VoltageLevel = 'normal' | 'low' | 'critical'

export const POSITION_DELAY_MINUTES = 15
export const VOLTAGE_NORMAL_MIN = 12.5
export const VOLTAGE_LOW_MIN = 11.5
export const BATTERY_GOOD_MIN = 70
export const BATTERY_LOW_MIN = 30

export const INDICATOR_TONE_CLASS: Record<IndicatorTone, string> = {
  success: 'text-success',
  warning: 'text-warning',
  danger: 'text-danger',
  muted: 'text-subtle',
}

export function connectionIndicator(vehicle: TrackingLiveVehicle, now: number): ConnectionIndicator {
  if (!vehicle.online) {
    return vehicle.position ? 'offline' : 'unknown'
  }

  const recordedAt = vehicle.position?.recorded_at

  if (!recordedAt) {
    return 'delayed'
  }

  const elapsed = now - new Date(recordedAt).getTime()

  return elapsed > POSITION_DELAY_MINUTES * 60_000 ? 'delayed' : 'online'
}

export function externalPowerStatus(vehicle: TrackingLiveVehicle): ExternalPowerStatus {
  const hasPowerCut = vehicleAlarms(vehicle).some((alarm) => alarm.code === 'powercut')

  if (hasPowerCut) {
    return 'disconnected'
  }

  const charging = vehicle.position?.charging

  if (charging === true) {
    return 'connected'
  }

  if (charging === false) {
    return 'disconnected'
  }

  return 'unknown'
}

export function gpsStatus(vehicle: TrackingLiveVehicle): GpsStatus {
  const valid = vehicle.position?.valid

  if (valid === true) {
    return 'valid'
  }

  if (valid === false) {
    return 'invalid'
  }

  return 'unknown'
}

export function batteryPercent(battery: number | null | undefined): number | null {
  if (battery === null || battery === undefined || Number.isNaN(battery)) {
    return null
  }

  if (battery <= 0 || battery > 100) {
    return null
  }

  if (battery < 30 && !Number.isInteger(battery)) {
    return null
  }

  return battery
}

export function signalLevel(signal: number | null | undefined): SignalLevel {
  if (signal === null || signal === undefined || Number.isNaN(signal)) {
    return 'unknown'
  }

  if (signal === 0) {
    return 'none'
  }

  if (signal < 0) {
    if (signal >= -75) return 'strong'
    if (signal >= -95) return 'medium'

    return 'weak'
  }

  if (signal <= 5) {
    if (signal >= 4) return 'strong'
    if (signal >= 2) return 'medium'

    return 'weak'
  }

  if (signal <= 31) {
    if (signal >= 16) return 'strong'
    if (signal >= 8) return 'medium'

    return 'weak'
  }

  if (signal >= 70) return 'strong'
  if (signal >= 40) return 'medium'

  return 'weak'
}

export function formatSignal(signal: number | null | undefined): string {
  if (signal === null || signal === undefined || Number.isNaN(signal)) {
    return 'Não informado'
  }

  if (signal < 0) {
    return `${signal} dBm`
  }

  if (signal <= 5) {
    return `${signal}/5`
  }

  if (signal <= 31) {
    return `${signal}/31`
  }

  return `${signal}%`
}

export function voltageLevel(voltage: number): VoltageLevel {
  if (voltage >= VOLTAGE_NORMAL_MIN) return 'normal'
  if (voltage >= VOLTAGE_LOW_MIN) return 'low'

  return 'critical'
}
