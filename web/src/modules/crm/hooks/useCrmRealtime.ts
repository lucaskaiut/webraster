import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { getEcho, isRealtimeConfigured } from '@/shared/realtime/echo'
import { queryKeys } from '@/shared/constants/query-keys'
import { useActiveTenant } from '@/shared/brand/useActiveTenant'

export function useCrmRealtime() {
  const queryClient = useQueryClient()
  const activeTenant = useActiveTenant()
  const tenantUuid = activeTenant?.id

  useEffect(() => {
    if (!tenantUuid || !isRealtimeConfigured()) {
      return
    }

    const echo = getEcho()
    if (echo === null) {
      return
    }

    const channelName = `tenant.${tenantUuid}.staff`
    const channel = echo.private(channelName)

    const invalidate = () => {
      void queryClient.invalidateQueries({ queryKey: queryKeys.crm.all })
    }

    channel.listen('.message.created', invalidate)
    channel.listen('.conversation.updated', invalidate)
    channel.listen('.lead.stage_changed', invalidate)

    return () => {
      channel.stopListening('.message.created')
      channel.stopListening('.conversation.updated')
      channel.stopListening('.lead.stage_changed')
      echo.leave(channelName)
    }
  }, [queryClient, tenantUuid])
}
