import {
  createContext,
  useContext,
  useLayoutEffect,
  useState,
  type Dispatch,
  type ReactNode,
  type SetStateAction,
} from 'react'

const TopbarSlotSetContext = createContext<Dispatch<SetStateAction<ReactNode>> | null>(null)
const TopbarSlotContext = createContext<ReactNode>(null)

export function TopbarSlotProvider({ children }: { children: ReactNode }) {
  const [slot, setSlot] = useState<ReactNode>(null)

  return (
    <TopbarSlotSetContext.Provider value={setSlot}>
      <TopbarSlotContext.Provider value={slot}>{children}</TopbarSlotContext.Provider>
    </TopbarSlotSetContext.Provider>
  )
}

export function useTopbarSlotContent(): ReactNode {
  return useContext(TopbarSlotContext)
}

/** Registra conteúdo no topbar da aplicação; limpa ao desmontar a página. */
export function useTopbarSlot(content: ReactNode | null) {
  const setSlot = useContext(TopbarSlotSetContext)

  useLayoutEffect(() => {
    if (!setSlot) return

    setSlot(content)
    return () => setSlot(null)
  }, [setSlot, content])
}
