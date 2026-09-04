import { create } from 'zustand'
import { persist } from 'zustand/middleware'

const STORAGE_KEY = 'nox:selected-tenant-id'

interface TenantContextState {
  selectedTenantId: string | null
  setSelectedTenantId: (id: string | null) => void
  clearSelectedTenantId: () => void
}

/**
 * Contexto de tenant selecionado (usuários master).
 * Persistido em localStorage para sobreviver a refresh.
 */
export const useTenantContextStore = create<TenantContextState>()(
  persist(
    (set) => ({
      selectedTenantId: null,
      setSelectedTenantId: (id) => set({ selectedTenantId: id }),
      clearSelectedTenantId: () => set({ selectedTenantId: null }),
    }),
    { name: STORAGE_KEY },
  ),
)
