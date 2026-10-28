import { create } from 'zustand'
import { persist } from 'zustand/middleware'

const STORAGE_KEY = 'webraster:tenant-branding'

export interface TenantBranding {
  id: string
  name: string
  logo_url: string | null
  favicon_url: string | null
}

interface TenantBrandingState {
  branding: TenantBranding | null
  setBranding: (branding: TenantBranding) => void
}

/**
 * Identidade visual do último tenant ativo (nome, logo e favicon).
 * Persistida em localStorage para ser reutilizada mesmo sem sessão —
 * por isso não deve conter dados sensíveis.
 */
export const useTenantBrandingStore = create<TenantBrandingState>()(
  persist(
    (set) => ({
      branding: null,
      setBranding: (branding) => set({ branding }),
    }),
    { name: STORAGE_KEY },
  ),
)

export function getTenantBranding(): TenantBranding | null {
  return useTenantBrandingStore.getState().branding
}
