import { format, parseISO } from 'date-fns'

const DEFAULT_DATE_FORMAT = 'yyyy-MM-dd'
const DEFAULT_TIME_FORMAT = 'HH:mm'

/**
 * Formats an ISO date/datetime string using the school's configured format
 * (settings key `localization.date_format`) when provided, falling back to
 * an ISO-like default so the UI never crashes on a missing setting.
 */
export function formatDate(value: string | null | undefined, pattern: string = DEFAULT_DATE_FORMAT): string {
  if (!value) return '—'
  try {
    return format(parseISO(value), pattern)
  } catch {
    return value
  }
}

export function formatDateTime(value: string | null | undefined, datePattern?: string, timePattern: string = DEFAULT_TIME_FORMAT): string {
  if (!value) return '—'
  try {
    return format(parseISO(value), `${datePattern ?? DEFAULT_DATE_FORMAT} ${timePattern}`)
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
