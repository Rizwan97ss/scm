import { env } from '@/config/env'

/**
 * True only on the bare marketing/platform domain (e.g. "localtest.me"),
 * never on a tenant subdomain (e.g. "greenwood.localtest.me") or on an
 * unrecognized host (localhost, a raw IP) -- those fall through to the
 * normal authenticated-app/login routes exactly as before this existed.
 */
export function isCentralDomain(): boolean {
  return !!env.centralDomain && window.location.hostname === env.centralDomain
}
