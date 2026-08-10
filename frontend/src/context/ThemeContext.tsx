import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchPublicSettings } from '@/api/endpoints/settings'
import { queryKeys } from '@/api/queryKeys'
import { env } from '@/config/env'

type ThemePreference = 'light' | 'dark' | 'system'

interface ThemeContextValue {
  appName: string
  logoUrl: string | null
  primaryColor: string
  secondaryColor: string
  currency: string
  dateFormat: string
  preference: ThemePreference
  setPreference: (preference: ThemePreference) => void
}

const ThemeContext = createContext<ThemeContextValue | null>(null)

const THEME_STORAGE_KEY = 'sms.theme-preference'

export function ThemeProvider({ children }: { children: ReactNode }) {
  const { data: settings } = useQuery({
    queryKey: queryKeys.publicSettings(),
    queryFn: () => fetchPublicSettings(),
    staleTime: 5 * 60 * 1000,
  })

  const [preference, setPreferenceState] = useState<ThemePreference>(
    () => (localStorage.getItem(THEME_STORAGE_KEY) as ThemePreference | null) ?? 'system'
  )

  const primaryColor = (settings?.['branding.primary_color'] as string | undefined) ?? '#2563eb'
  const secondaryColor = (settings?.['branding.secondary_color'] as string | undefined) ?? '#0f172a'
  const logoUrl = (settings?.['branding.logo_url'] as string | undefined) || null
  const currency = (settings?.['localization.currency'] as string | undefined) ?? 'USD'
  const dateFormat = (settings?.['localization.date_format'] as string | undefined) ?? 'yyyy-MM-dd'

  useEffect(() => {
    document.documentElement.style.setProperty('--brand-primary', primaryColor)
    document.documentElement.style.setProperty('--brand-secondary', secondaryColor)
  }, [primaryColor, secondaryColor])

  useEffect(() => {
    const root = document.documentElement
    if (preference === 'system') {
      root.removeAttribute('data-theme')
    } else {
      root.setAttribute('data-theme', preference)
    }
    localStorage.setItem(THEME_STORAGE_KEY, preference)
  }, [preference])

  const value = useMemo<ThemeContextValue>(
    () => ({
      appName: (settings?.['branding.app_name'] as string | undefined) ?? env.appName,
      logoUrl,
      primaryColor,
      secondaryColor,
      currency,
      dateFormat,
      preference,
      setPreference: setPreferenceState,
    }),
    [settings, logoUrl, primaryColor, secondaryColor, currency, dateFormat, preference]
  )

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext)
  if (!ctx) throw new Error('useTheme must be used within a ThemeProvider')
  return ctx
}
