import { getLocalizationDefaults } from './localizationDefaults'

/**
 * Formats a number as currency using the school's configured currency code
 * (settings key `localization.currency`, e.g. "USD") — via
 * `getLocalizationDefaults()`, kept in sync by `ThemeContext`, so a call
 * site never needs to thread the setting through itself. Pass `currency`
 * explicitly only to override the school's default for a specific case.
 * Falls back to USD (`localizationDefaults`'s own default) before settings
 * have loaded, so amounts still render sensibly on first paint.
 */
export function formatCurrency(amount: number, currency?: string, locale = 'en-US'): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: currency ?? getLocalizationDefaults().currency }).format(amount)
}
