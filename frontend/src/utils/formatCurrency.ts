/**
 * Formats a number as currency using the school's configured currency code
 * (settings key `localization.currency`, e.g. "USD"). Falls back to USD so
 * amounts still render sensibly before settings have loaded.
 */
export function formatCurrency(amount: number, currency = 'USD', locale = 'en-US'): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(amount)
}
