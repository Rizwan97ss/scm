import { format, parseISO } from 'date-fns'
import { getLocalizationDefaults } from './localizationDefaults'

/**
 * Formats an ISO date/datetime string using the school's configured format
 * (settings key `localization.date_format`) via `getLocalizationDefaults()`
 * — kept in sync by `ThemeContext` — so a call site never needs to thread
 * the setting through itself. Pass `pattern` explicitly only to override
 * the school's default for a specific case (e.g. a compact table column).
 * Falls back to an ISO-like default (`localizationDefaults`'s own default)
 * before settings have loaded, so the UI never crashes on a missing one.
 */
export function formatDate(value: string | null | undefined, pattern?: string): string {
  if (!value) return '—'
  try {
    return format(parseISO(value), pattern ?? getLocalizationDefaults().dateFormat)
  } catch {
    return value
  }
}

export function formatDateTime(value: string | null | undefined, datePattern?: string, timePattern?: string): string {
  if (!value) return '—'
  try {
    const defaults = getLocalizationDefaults()
    return format(parseISO(value), `${datePattern ?? defaults.dateFormat} ${timePattern ?? defaults.timeFormat}`)
  } catch {
    return value
  }
}

/**
 * `<input type="datetime-local">` only ever speaks in naive local wall-clock
 * strings ("2026-08-13T19:57") with no timezone info — never bind an API's
 * UTC ISO string to one directly, or the value silently gets reinterpreted
 * as if it were already in the browser's local zone (a School Admin in
 * UTC+5 typing "7:57 PM" would have it saved as 19:57 UTC — 5 hours off
 * from what they meant). These two converters are the only safe boundary
 * for that field type; `new Date(...)`'s local getters and the no-suffix
 * constructor form do the local<->UTC conversion correctly on both sides.
 */
export function toDatetimeLocalInput(isoUtc: string | null | undefined): string {
  if (!isoUtc) return ''
  const d = new Date(isoUtc)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export function fromDatetimeLocalInput(local: string): string | null {
  if (!local) return null
  const d = new Date(local)
  return Number.isNaN(d.getTime()) ? null : d.toISOString()
}
