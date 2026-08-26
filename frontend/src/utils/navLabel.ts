/**
 * Derives an i18next `nav.*` key from a nav item's canonical English label
 * (see config/navigation.ts) — e.g. "HR & Payroll" -> "nav.hr_payroll".
 * Labels stay plain English strings in navigation.ts itself (they're also
 * used as React keys and GlobalSearch's index text), so this is computed
 * at render time rather than hand-maintaining a parallel key on every nav
 * entry. Every locale's translation.json "nav" namespace must define a key
 * matching this exact algorithm for each label in navigation.ts.
 */
export function navLabelKey(label: string): string {
  return 'nav.' + label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '')
}
