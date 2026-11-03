import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router'
import { AppProviders } from '@/app/providers/AppProviders'
import { router } from '@/app/router'
import { applyFavicon } from '@/shared/brand/favicon'
import { getTenantBranding } from '@/shared/stores/tenant-branding.store'
import { applyTheme } from '@/shared/theme/theme'
import './index.css'

const CHUNK_RELOAD_KEY = 'app:chunk-reload-at'
const CHUNK_RELOAD_COOLDOWN_MS = 10_000

// Depois de um deploy, abas abertas podem pedir chunks com hash antigo e
// falhar. Um reload único (com cooldown para não entrar em loop) resolve.
window.addEventListener('vite:preloadError', (event) => {
  event.preventDefault()

  let lastReload = 0

  try {
    lastReload = Number(sessionStorage.getItem(CHUNK_RELOAD_KEY) ?? '0')
  } catch {
    lastReload = 0
  }

  if (Number.isFinite(lastReload) && Date.now() - lastReload < CHUNK_RELOAD_COOLDOWN_MS) {
    return
  }

  try {
    sessionStorage.setItem(CHUNK_RELOAD_KEY, String(Date.now()))
  } catch {
    // sessionStorage indisponível: segue com o reload mesmo assim.
  }

  window.location.reload()
})

// Aplica favicon e tema do último tenant ativo antes do primeiro render
// (evita o "piscar" das cores padrão até a sessão carregar).
applyFavicon(getTenantBranding()?.favicon_url)
applyTheme(getTenantBranding()?.theme)

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AppProviders>
      <RouterProvider router={router} />
    </AppProviders>
  </StrictMode>,
)
