import { Monitor, Moon, Sun } from 'lucide-react'
import { useThemeStore, type ThemePreference } from '@/shared/stores/theme.store'
import { Dropdown, DropdownItem } from './Dropdown'

const OPTIONS: Array<{ value: ThemePreference; label: string; icon: typeof Sun }> = [
  { value: 'light', label: 'Claro', icon: Sun },
  { value: 'dark', label: 'Escuro', icon: Moon },
  { value: 'system', label: 'Sistema', icon: Monitor },
]

export function ThemeToggle() {
  const theme = useThemeStore((state) => state.theme)
  const setTheme = useThemeStore((state) => state.setTheme)

  const current = OPTIONS.find((option) => option.value === theme) ?? OPTIONS[2]
  const CurrentIcon = current.icon

  return (
    <Dropdown
      label="Alterar tema"
      trigger={
        <span className="flex size-9 items-center justify-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-foreground">
          <CurrentIcon className="size-4.5" aria-hidden="true" />
        </span>
      }
    >
      {OPTIONS.map((option) => (
        <DropdownItem key={option.value} icon={option.icon} onSelect={() => setTheme(option.value)}>
          {option.label}
        </DropdownItem>
      ))}
    </Dropdown>
  )
}
