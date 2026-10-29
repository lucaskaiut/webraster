import axios from 'axios'
import { API_BASE_URL } from '@/shared/api/base-url'
import { PANEL, PANEL_HEADER } from '@/shared/api/panel'

let csrfReady = false

/**
 * Garante o cookie XSRF do painel antes de requisições de autenticação
 * (fluxo SPA do Laravel Sanctum).
 */
export async function ensureCsrfCookie(): Promise<void> {
  if (csrfReady) return

  await axios.get(`${API_BASE_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
    headers: { [PANEL_HEADER]: PANEL },
  })

  csrfReady = true
}
