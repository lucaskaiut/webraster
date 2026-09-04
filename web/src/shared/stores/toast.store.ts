import { create } from 'zustand'

export type ToastType = 'success' | 'error' | 'info' | 'warning'

export interface ToastItem {
  id: number
  type: ToastType
  title: string
  description?: string
}

interface ToastState {
  toasts: ToastItem[]
  push: (toast: Omit<ToastItem, 'id'>) => void
  dismiss: (id: number) => void
}

const TOAST_DURATION = 5000

let nextId = 0

export const useToastStore = create<ToastState>()((set, get) => ({
  toasts: [],

  push: (item) => {
    const id = ++nextId

    set((state) => ({ toasts: [...state.toasts.slice(-4), { ...item, id }] }))

    window.setTimeout(() => get().dismiss(id), TOAST_DURATION)
  },

  dismiss: (id) => set((state) => ({ toasts: state.toasts.filter((t) => t.id !== id) })),
}))

export const toast = {
  success: (title: string, description?: string) =>
    useToastStore.getState().push({ type: 'success', title, description }),
  error: (title: string, description?: string) =>
    useToastStore.getState().push({ type: 'error', title, description }),
  info: (title: string, description?: string) =>
    useToastStore.getState().push({ type: 'info', title, description }),
  warning: (title: string, description?: string) =>
    useToastStore.getState().push({ type: 'warning', title, description }),
}
