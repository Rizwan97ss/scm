import { useTranslation } from 'react-i18next'
import { Check, Languages } from 'lucide-react'
import { Dropdown, type DropdownItem } from '@/components/ui/Dropdown'
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
    icon: lang.code === i18n.language ? <Check className="h-4 w-4" /> : <span className="h-4 w-4" />,
    onSelect: () => i18n.changeLanguage(lang.code),
  }))

  return (
    <Dropdown
      trigger={
        <button
          type="button"
          className="flex h-9 w-9 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label={t('common.language')}
        >
          <Languages className="h-4 w-4" />
        </button>
      }
      items={items}
    />
  )
}
