import { env } from '@/config/env'

/**
 * Builds an absolute URL for a file download link (PDF receipts, report
 * cards, ID cards, ...) meant to be used directly as an <a href> or
 * window.open() target — NOT through httpClient, since those are plain
 * browser navigations, not axios requests.
 *
 * A bare relative path like `/payslips/1/receipt/pdf` resolves against the
 * SPA's own origin (e.g. http://localhost:5173), not the API
 * (http://localhost:8000/api/v1) — there's no dev-server proxy bridging the
 * two, so a real click 404s against the SPA's own router instead of
 * reaching the backend. Every `*PdfUrl` helper must go through this.
 */
export function apiFileUrl(path: string): string {
  return `${env.apiUrl}/v1${path}`
}
