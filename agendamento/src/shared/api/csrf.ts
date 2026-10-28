import axios from 'axios'
import { API_BASE_URL } from '@/shared/api/base-url'

let csrfReady = false

/**
 * Garante o cookie XSRF-TOKEN antes de requisições de autenticação
 * (fluxo SPA do Laravel Sanctum).
 */
export async function ensureCsrfCookie(): Promise<void> {
  if (csrfReady) return

  await axios.get(`${API_BASE_URL}/sanctum/csrf-cookie`, { withCredentials: true })

  csrfReady = true
}
