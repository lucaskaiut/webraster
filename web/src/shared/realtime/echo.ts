import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { http } from '@/shared/api/http'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

let echo: Echo<'reverb'> | null = null

export function isRealtimeConfigured(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY && import.meta.env.VITE_REVERB_HOST)
}

export function getEcho(): Echo<'reverb'> | null {
  if (!isRealtimeConfigured()) {
    return null
  }

  if (echo !== null) {
    return echo
  }

  window.Pusher = Pusher

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        http
          .post<{ auth: string }>('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name,
          })
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null))
      },
    }),
  })

  return echo
}
