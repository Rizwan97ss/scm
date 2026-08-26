import { useTranslation } from 'react-i18next'
import { Moon, Sun, SunMoon } from 'lucide-react'
import { Dropdown, type DropdownItem } from '@/components/ui/Dropdown'
import { Button } from '@/components/ui/Button'
import { useTheme } from '@/context/ThemeContext'

/**
 * The light/dark/system theming itself (CSS tokens, `data-theme` attribute,
 * localStorage persistence) already existed in ThemeContext — this was the
 * missing piece: nothing anywhere called `setPreference`, so a viewer could
 * only ever get dark mode by matching OS-level `prefers-color-scheme`, never
 * by choosing it. Mirrors LanguageSwitcher's placement/shape (see Topbar.tsx,
 * PlatformShell.tsx) so the two sit together consistently across shells.
 */
export function ThemeSwitcher() {
  const { t } = useTranslation()
  const { preference, setPreference } = useTheme()

  const items: DropdownItem[] = [
    { label: t('common.themeLight'), icon: <Sun className="h-4 w-4" />, onSelect: () => setPreference('light') },
    { label: t('common.themeDark'), icon: <Moon className="h-4 w-4" />, onSelect: () => setPreference('dark') },
    { label: t('common.themeSystem'), icon: <SunMoon className="h-4 w-4" />, onSelect: () => setPreference('system') },
  ]

  const Icon = preference === 'light' ? Sun : preference === 'dark' ? Moon : SunMoon

  return (
    <Dropdown
      trigger={
        <Button variant="ghost" size="icon" aria-label={t('common.theme')}>
          <Icon className="h-5 w-5" />
        </Button>
      }
      items={items}
    />
  )
}
