/**
 * The school's configured `localization.*` settings (currency code,
 * date-fns date/time patterns) — `ThemeContext` already fetches and parses
 * these reactively for its own consumers (`useTheme()`), but `formatDate`/
 * `formatCurrency` are plain utility functions, not hooks, called from
 * ~60 places across the app that were never going to be migrated to read
 * from React context individually. This module-level cache is the bridge:
 * `ThemeProvider` keeps it in sync via `setLocalizationDefaults` whenever
 * settings load or change, and the formatters read from it as their
 * fallback when no explicit override is passed — every existing call site
 * picks up the school's actual configured format/currency for free, with
 * no call-site changes needed, instead of the hardcoded `USD`/`yyyy-MM-dd`
 * they silently rendered before regardless of what Settings actually said.
 */
export interface LocalizationDefaults {
  currency: string
  dateFormat: string
  timeFormat: string
}

let current: LocalizationDefaults = {
  currency: 'USD',
  dateFormat: 'yyyy-MM-dd',
  timeFormat: 'HH:mm',
}

export function setLocalizationDefaults(next: LocalizationDefaults): void {
  current = next
}

export function getLocalizationDefaults(): LocalizationDefaults {
  return current
}
