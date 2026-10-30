import { useEffect, useRef } from 'react'
import type { AppNotification, AlertSoundGroup } from '@/shared/types/models'
import { playAlertSound, type AlertSoundKey } from '@/shared/utils/alert-sound'
import { useAlertSoundPreferencesQuery } from '@/modules/alerts/hooks/useAlerts'
import { useNotificationsQuery } from './useNotifications'

/** Grupo de som da notificação, espelhando o AlertSoundService do backend. */
function groupFor(notification: AppNotification): string | null {
  if (notification.type === 'ignition_on' || notification.type === 'ignition_off') return 'ignition'
  if (notification.type === 'geofence') return 'geofence'
  if (notification.type === 'battery_tamper') return 'powercut'

  if (notification.type === 'device_alarm') {
    const meta = notification.alert?.meta
    const code = meta && typeof meta === 'object' ? (meta as Record<string, unknown>).alarm_code : null

    if (typeof code === 'string' && code.toLowerCase().trim() === 'powercut') return 'powercut'
  }

  return null
}

function soundFor(notification: AppNotification, groups: AlertSoundGroup[] | undefined): AlertSoundKey {
  const group = groupFor(notification)
  const sound = group ? groups?.find((item) => item.type === group)?.sound : null

  if (sound === 'chime' || sound === 'alert' || sound === 'siren') {
    return sound
  }

  return 'default'
}

/**
 * Toca o som escolhido pelo usuário quando uma nova notificação de alerta
 * chega pelo polling. A primeira carga apenas registra as notificações
 * existentes — o som é reservado para alertas novos enquanto o painel está
 * aberto.
 */
export function useNotificationAlertSound(enabled: boolean): void {
  const query = useNotificationsQuery({ per_page: 20 })
  const preferences = useAlertSoundPreferencesQuery(enabled)
  const seenIds = useRef<Set<string> | null>(null)

  useEffect(() => {
    if (!enabled || !query.data) return

    const items = query.data.data

    if (seenIds.current === null) {
      seenIds.current = new Set(items.map((item) => item.id))
      return
    }

    const seen = seenIds.current
    const fresh = items.filter((item) => !seen.has(item.id))

    if (fresh.length === 0) return

    fresh.forEach((item) => seen.add(item.id))

    const unread = fresh.filter((item) => !item.read_at)
    if (unread.length === 0) return

    const groups = preferences.data?.alerts
    const bySeverity = [...unread].sort((left, right) => {
      const leftCritical = left.data?.severity === 'critical' ? 1 : 0
      const rightCritical = right.data?.severity === 'critical' ? 1 : 0

      return rightCritical - leftCritical
    })

    playAlertSound(soundFor(bySeverity[0]!, groups))
  }, [enabled, query.data, preferences.data])
}
