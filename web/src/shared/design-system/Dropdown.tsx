import { useEffect, useRef, useState, type ReactNode } from 'react'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/shared/utils/cn'

export function Dropdown({
  trigger,
  children,
  align = 'end',
  label,
}: {
  trigger: ReactNode
  children: ReactNode
  align?: 'start' | 'end'
  label?: string
}) {
  const [open, setOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) setOpen(false)
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  return (
    <div ref={containerRef} className="relative">
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-label={label}
        onClick={() => setOpen((value) => !value)}
        className="flex cursor-pointer items-center rounded-lg transition-colors"
      >
        {trigger}
      </button>

      {open && (
        <div
          role="menu"
          className={cn(
            'animate-fade-in absolute z-40 mt-2 min-w-48 rounded-xl bg-surface p-1.5 shadow-pop',
            align === 'end' ? 'right-0' : 'left-0',
          )}
          onClick={() => setOpen(false)}
        >
          {children}
        </div>
      )}
    </div>
  )
}

export function DropdownItem({
  icon: Icon,
  children,
  onSelect,
  danger = false,
  disabled = false,
}: {
  icon?: LucideIcon
  children: ReactNode
  onSelect?: () => void
  danger?: boolean
  disabled?: boolean
}) {
  return (
    <button
      type="button"
      role="menuitem"
      disabled={disabled}
      onClick={onSelect}
      className={cn(
        'flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition-colors',
        'disabled:cursor-not-allowed disabled:opacity-50',
        danger ? 'text-danger hover:bg-danger-soft' : 'text-foreground hover:bg-surface-2',
      )}
    >
      {Icon && <Icon className="size-4 shrink-0" aria-hidden="true" />}
      {children}
    </button>
  )
}

export function DropdownSeparator() {
  return <div className="my-1.5 h-px bg-surface-2" role="separator" />
}
