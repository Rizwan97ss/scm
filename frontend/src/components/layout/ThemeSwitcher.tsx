import { Check, Monitor, Moon, Sun } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Dropdown, type DropdownItem } from '@/components/ui/Dropdown'
import { useTheme } from '@/context/ThemeContext'

const OPTIONS = [
  { value: 'light', labelKey: 'common.themeLight', icon: Sun },
  { value: 'dark', labelKey: 'common.themeDark', icon: Moon },
  { value: 'system', labelKey: 'common.themeSystem', icon: Monitor },
] as const

export function ThemeSwitcher() {
  const { t } = useTranslation()
  const { preference, setPreference } = useTheme()
  const ActiveIcon = OPTIONS.find((o) => o.value === preference)?.icon ?? Monitor

  const items: DropdownItem[] = OPTIONS.map((option) => ({
    label: t(option.labelKey),
    icon: option.value === preference ? <Check className="h-4 w-4" /> : <option.icon className="h-4 w-4" />,
    onSelect: () => setPreference(option.value),
  }))

  return (
    <Dropdown
      trigger={
        <button
          type="button"
          className="flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label={t('common.theme')}
        >
          <ActiveIcon className="h-4 w-4" />
        </button>
      }
      items={items}
    />
  )
}
