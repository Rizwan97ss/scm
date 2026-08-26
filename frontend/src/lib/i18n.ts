import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import LanguageDetector from 'i18next-browser-languagedetector'
import HttpBackend from 'i18next-http-backend'

/**
 * Every supported language, with the metadata the LanguageSwitcher and RTL
 * layout logic both need — one source of truth so adding a 19th language
 * later is "add a JSON file + one entry here", not a hunt across the app.
 */
export const SUPPORTED_LANGUAGES = [
  { code: 'en', label: 'English', nativeLabel: 'English', dir: 'ltr' },
  { code: 'ur', label: 'Urdu', nativeLabel: 'اردو', dir: 'rtl' },
  { code: 'hi', label: 'Hindi', nativeLabel: 'हिन्दी', dir: 'ltr' },
  { code: 'jv', label: 'Javanese', nativeLabel: 'Basa Jawa', dir: 'ltr' },
  { code: 'ko', label: 'Korean', nativeLabel: '한국어', dir: 'ltr' },
  { code: 'fr', label: 'French', nativeLabel: 'Français', dir: 'ltr' },
  { code: 'de', label: 'German', nativeLabel: 'Deutsch', dir: 'ltr' },
  { code: 'ar', label: 'Arabic', nativeLabel: 'العربية', dir: 'rtl' },
  { code: 'es', label: 'Spanish', nativeLabel: 'Español', dir: 'ltr' },
  { code: 'pt', label: 'Portuguese', nativeLabel: 'Português', dir: 'ltr' },
  { code: 'zh', label: 'Chinese (Simplified)', nativeLabel: '中文（简体）', dir: 'ltr' },
  { code: 'ru', label: 'Russian', nativeLabel: 'Русский', dir: 'ltr' },
  { code: 'ja', label: 'Japanese', nativeLabel: '日本語', dir: 'ltr' },
  { code: 'bn', label: 'Bengali', nativeLabel: 'বাংলা', dir: 'ltr' },
  { code: 'tr', label: 'Turkish', nativeLabel: 'Türkçe', dir: 'ltr' },
  { code: 'id', label: 'Indonesian', nativeLabel: 'Bahasa Indonesia', dir: 'ltr' },
  { code: 'ms', label: 'Malay', nativeLabel: 'Bahasa Melayu', dir: 'ltr' },
  { code: 'zh-TW', label: 'Chinese (Traditional)', nativeLabel: '中文（繁體）', dir: 'ltr' },
] as const

export type SupportedLanguageCode = (typeof SUPPORTED_LANGUAGES)[number]['code']

export const RTL_LANGUAGES: Set<SupportedLanguageCode> = new Set(
  SUPPORTED_LANGUAGES.filter((l) => l.dir === 'rtl').map((l): SupportedLanguageCode => l.code)
)

export function isRtl(code: string): boolean {
  return RTL_LANGUAGES.has(code as SupportedLanguageCode)
}

/**
 * localStorage key shared with the axios client (see api/client.ts), which
 * reads it directly to set an X-Locale header on every request — the
 * backend has no per-user locale column, so this is how a chosen language
 * reaches server-rendered strings (validation errors, notifications, PDFs)
 * without one. i18next-browser-languagedetector reads/writes this same key
 * (configured below), so the two never drift apart.
 */
export const LOCALE_STORAGE_KEY = 'sms.language'

/**
 * Locale JSON is fetched at runtime (public/locales/{{lng}}/translation.json)
 * rather than statically imported — with 18 languages' worth of strings now
 * covering a growing share of the app, bundling every language into every
 * user's initial JS (the old approach) means shipping ~18x the translation
 * payload nobody but that one active language needs. i18next-http-backend +
 * react-i18next's Suspense integration (see AppProviders' <Suspense>
 * wrapper) makes this a transparent swap: no component's t() call changes,
 * the app just suspends briefly on first paint (and on a language switch)
 * while the active language's single JSON file loads.
 */
i18n
  .use(HttpBackend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    backend: {
      loadPath: '/locales/{{lng}}/translation.json',
    },
    fallbackLng: 'en',
    supportedLngs: SUPPORTED_LANGUAGES.map((l) => l.code),
    detection: {
      order: ['localStorage', 'navigator'],
      lookupLocalStorage: LOCALE_STORAGE_KEY,
      caches: ['localStorage'],
    },
    interpolation: { escapeValue: false },
    react: {
      useSuspense: true,
    },
  })

/**
 * Keeps <html lang>/<html dir> in sync with the active language. Shared UI
 * primitives (Table, DataTable, Drawer, Select, FormField, GlobalSearch,
 * NotificationBell) use logical Tailwind utilities (ps-/pe-/ms-/me-/start-/
 * end-/text-start/text-end) rather than physical left/right ones, so they
 * repaint correctly the moment `dir` flips — no separate `[dir="rtl"]`
 * override stylesheet needed. This has to run on every language change, not
 * just once at boot; it's subscribed on the i18next singleton itself rather
 * than in a React effect so it's correct even for the very first paint,
 * before AppProviders has mounted.
 */
function applyDocumentDirection(language: string): void {
  document.documentElement.lang = language
  document.documentElement.dir = isRtl(language) ? 'rtl' : 'ltr'
}

i18n.on('languageChanged', applyDocumentDirection)
applyDocumentDirection(i18n.language)

export default i18n
