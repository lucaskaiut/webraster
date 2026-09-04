import { create } from 'zustand'

const STORAGE_KEY = 'assistant.lastReadAt'

function readLastReadAt(): string {
  return localStorage.getItem(STORAGE_KEY) ?? ''
}

interface AssistantStore {
  unreadCount: number
  lastReadAt: string
  markRead: () => void
  setUnreadCount: (count: number) => void
}

export const useAssistantStore = create<AssistantStore>()((set) => ({
  unreadCount: 0,
  lastReadAt: readLastReadAt(),

  markRead: () => {
    const now = new Date().toISOString()
    localStorage.setItem(STORAGE_KEY, now)
    set({ lastReadAt: now, unreadCount: 0 })
  },

  setUnreadCount: (count) => set({ unreadCount: count }),
}))
