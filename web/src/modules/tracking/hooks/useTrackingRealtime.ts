import { useEffect } from 'react'
import { useQueryClient, type QueryClient } from '@tanstack/react-query'
import { getEcho, isRealtimeConfigured } from '@/shared/realtime/echo'
import { useRealtimeStore } from '@/shared/realtime/realtime.store'
import { useSessionStore } from '@/shared/stores/session.store'
import { useTenantContextStore } from '@/shared/stores/tenant.store'
import type { GpsPosition, TrackingLiveVehicle } from '@/shared/types/models'

export interface PositionUpdatedPayload {
  vehicle_id: string
  online: boolean
  position: GpsPosition
}

function patchLiveVehicles(queryClient: QueryClient, update: PositionUpdatedPayload): void {
  queryClient.setQueriesData<TrackingLiveVehicle[]>(
    { queryKey: ['tracking', 'live'] },
    (current) => {
      if (!Array.isArray(current)) {
        return current
      }

      let changed = false

      const next = current.map((vehicle) => {
        if (vehicle.id !== update.vehicle_id) {
          return vehicle
        }

        changed = true

        return {
          ...vehicle,
          online: update.online,
          position: update.position,
        }
      })

      return changed ? next : current
    },
  )
}

export function useTrackingRealtime(enabled = true): void {
  const queryClient = useQueryClient()
  const isMaster = useSessionStore((state) => state.isMaster)
  const homeTenantId = useSessionStore((state) => state.tenant?.id ?? null)
  const clientId = useSessionStore((state) => state.user?.client_id ?? null)
  const selectedTenantId = useTenantContextStore((state) => state.selectedTenantId)
  const setConnected = useRealtimeStore((state) => state.setConnected)

  const tenantId = isMaster && selectedTenantId ? selectedTenantId : homeTenantId

  useEffect(() => {
    if (!enabled || !isRealtimeConfigured()) {
      return
    }

    if (clientId === null && tenantId === null) {
      return
    }

    const echo = getEcho()

    if (echo === null) {
      return
    }

    const connection = echo.connector.pusher.connection
    const handleDisconnected = () => setConnected(false)

    connection.bind('disconnected', handleDisconnected)
    connection.bind('unavailable', handleDisconnected)

    const channelName = clientId ? `client.${clientId}` : `tenant.${tenantId}.staff`
    const channel = echo.private(channelName)

    channel.subscribed(() => setConnected(true))
    channel.error(() => setConnected(false))

    channel.listen('.position.updated', (payload: PositionUpdatedPayload) => {
      patchLiveVehicles(queryClient, payload)
    })

    return () => {
      channel.stopListening('.position.updated')
      echo.leave(channelName)
      connection.unbind('disconnected', handleDisconnected)
      connection.unbind('unavailable', handleDisconnected)
      setConnected(false)
    }
  }, [enabled, clientId, tenantId, queryClient, setConnected])
}
