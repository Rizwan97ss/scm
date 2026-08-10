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
