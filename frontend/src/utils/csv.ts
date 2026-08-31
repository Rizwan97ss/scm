/** Quotes a field only when it needs it (contains a comma, quote, or newline), doubling any internal quotes — standard CSV escaping (RFC 4180). */
function csvEscape(value: string): string {
  if (/[",\n]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`
  }
  return value
}

/**
 * Turns a PasteGrid's rows into the same CSV shape a file upload would
 * produce, so the grid can be validated/committed through the exact same
 * backend import endpoint (and its exact validation rules) as a real
 * uploaded file — see ImportForm.
 */
export function rowsToCsvFile(columns: string[], rows: string[][], filename: string): File {
  const lines = [
    columns.map(csvEscape).join(','),
    ...rows.map((row) => columns.map((_, index) => csvEscape(row[index] ?? '')).join(',')),
  ]
  return new File([lines.join('\n')], filename, { type: 'text/csv' })
}
