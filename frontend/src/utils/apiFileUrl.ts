import { apiUrl } from '@/api/client'

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
 *
 * Must use client.ts's runtime-resolved `apiUrl`, not `env.apiUrl` directly
 * — the raw env value only carries the build-time scheme/port template; the
 * hostname has to follow window.location.hostname at click time or every
 * one of these links points at whatever host was baked in at build time
 * (e.g. localhost) instead of the tenant subdomain the page is actually on,
 * and tenancy can't resolve a school for that host ("School not found.").
 */
export function apiFileUrl(path: string): string {
  return `${apiUrl}/v1${path}`
}
