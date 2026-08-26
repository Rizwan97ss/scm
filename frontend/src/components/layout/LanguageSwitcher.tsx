import { useTranslation } from 'react-i18next'
import { Globe } from 'lucide-react'
import { Dropdown, type DropdownItem } from '@/components/ui/Dropdown'
import { Button } from '@/components/ui/Button'
import { SUPPORTED_LANGUAGES } from '@/lib/i18n'

/**
 * Shown in every shell (tenant app, platform console, marketing/auth
 * pages) — see Topbar.tsx, PlatformTopbar equivalent, and MarketingNav.
 * Switching language is purely a localStorage preference (see
 * lib/i18n.ts's LOCALE_STORAGE_KEY docblock); there's no per-user DB
 * column for it, so this works identically for a logged-out visitor and
 * an authenticated user.
 */
export function LanguageSwitcher() {
  const { i18n, t } = useTranslation()

  const items: DropdownItem[] = SUPPORTED_LANGUAGES.map((lang) => ({
    label: lang.nativeLabel,
    onSelect: () => i18n.changeLanguage(lang.code),
  }))

  return (
    <Dropdown
      trigger={
        <Button variant="ghost" size="icon" aria-label={t('common.language')}>
          <Globe className="h-5 w-5" />
        </Button>
      }
      items={items}
    />
  )
}
