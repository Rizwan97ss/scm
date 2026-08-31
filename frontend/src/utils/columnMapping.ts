/** Lowercase, strip everything but letters/digits — "Department Name", "department_name", and "Dept. Name" all normalize the same way, close enough for a *suggested* match a human still confirms. */
function normalize(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9]/g, '')
}

/**
 * Suggests which canonical column each of the uploaded file's own headers
 * maps to — a starting point for ImportFileMapper's dropdowns, never
 * applied without the user confirming it. Exact normalized match first,
 * then substring containment either direction (covers "Dept Code" →
 * "code", "code" → "Department Code"). Each canonical column is only
 * suggested once — if two file headers would otherwise both match "code",
 * the first (in file-header order) gets it and the second is left
 * unmapped for the user to resolve by hand rather than silently guessing
 * which one is right.
 *
 * @returns one entry per `fileHeaders` — the matched canonical column, or null
 */
export function guessColumnMapping(fileHeaders: string[], canonicalColumns: string[]): (string | null)[] {
  const normalizedCanonical = canonicalColumns.map(normalize)
  const claimed = new Set<number>()

  return fileHeaders.map((header) => {
    const normalizedHeader = normalize(header)
    if (!normalizedHeader) return null

    let bestIndex = normalizedCanonical.findIndex((c, i) => !claimed.has(i) && c === normalizedHeader)

    if (bestIndex === -1) {
      bestIndex = normalizedCanonical.findIndex(
        (c, i) => !claimed.has(i) && c.length > 0 && (normalizedHeader.includes(c) || c.includes(normalizedHeader))
      )
    }

    if (bestIndex === -1) return null

    claimed.add(bestIndex)
    return canonicalColumns[bestIndex]
  })
}
