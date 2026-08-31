import { Plus, X } from 'lucide-react'
import { Button } from './Button'
import { cn } from '@/utils/cn'

export interface PasteGridProps {
  /** Column headers — must exactly match the backend import's expected field names (see each entity's *ImportTemplateExport::headings()), since a validation failure's `attribute` is matched against these to highlight the offending cell. */
  columns: string[]
  rows: string[][]
  onRowsChange: (rows: string[][]) => void
  /** Keyed `"{rowIndex}:{column}"` — rowIndex is 0-based against `rows`, not the backend's 1-indexed-with-header row number. See ImportForm's failure-to-cell mapping. */
  cellErrors?: Map<string, string>
  disabled?: boolean
}

function emptyRow(columns: string[]): string[] {
  return columns.map(() => '')
}

/**
 * An editable spreadsheet-style grid for typing or pasting rows directly
 * (e.g. copied from Excel/Google Sheets) instead of uploading a file — see
 * ImportForm, which converts the grid's contents into the same CSV shape a
 * file upload would produce and runs it through the exact same
 * preview/confirm backend flow, so every validation rule and the resulting
 * per-cell error highlighting come from the real import class, not a
 * separate client-side copy of the rules.
 */
export function PasteGrid({ columns, rows, onRowsChange, cellErrors, disabled }: PasteGridProps) {
  function updateCell(rowIndex: number, colIndex: number, value: string) {
    const next = rows.map((row) => [...row])
    next[rowIndex] = next[rowIndex] ?? emptyRow(columns)
    next[rowIndex][colIndex] = value
    onRowsChange(next)
  }

  function addRow() {
    onRowsChange([...rows, emptyRow(columns)])
  }

  function removeRow(rowIndex: number) {
    onRowsChange(rows.filter((_, i) => i !== rowIndex))
  }

  /** A single-cell paste (no tabs/newlines) falls through to the browser's default input behavior — only a real multi-cell spreadsheet paste is intercepted and distributed starting at the focused cell, standard spreadsheet-paste UX. */
  function handlePaste(event: React.ClipboardEvent<HTMLInputElement>, rowIndex: number, colIndex: number) {
    const text = event.clipboardData.getData('text')
    if (!text.includes('\t') && !text.includes('\n')) return

    event.preventDefault()
    const pastedRows = text
      .replace(/\r/g, '')
      .split('\n')
      .filter((line, index, all) => !(index === all.length - 1 && line === '')) // trailing newline from most spreadsheet copies
      .map((line) => line.split('\t'))

    const next = rows.map((row) => [...row])
    pastedRows.forEach((pastedRow, i) => {
      const targetRow = rowIndex + i
      while (next.length <= targetRow) next.push(emptyRow(columns))
      pastedRow.forEach((value, j) => {
        const targetCol = colIndex + j
        if (targetCol < columns.length) next[targetRow][targetCol] = value
      })
    })
    onRowsChange(next)
  }

  return (
    <div className="rounded-md border border-border">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
            <tr>
              {columns.map((column) => (
                <th key={column} className="whitespace-nowrap p-2 text-start font-medium">
                  {column}
                </th>
              ))}
              <th className="w-10" />
            </tr>
          </thead>
          <tbody>
            {rows.map((row, rowIndex) => (
              <tr key={rowIndex} className="border-t border-border">
                {columns.map((column, colIndex) => {
                  const error = cellErrors?.get(`${rowIndex}:${column}`)
                  return (
                    <td key={column} className="p-0">
                      <input
                        value={row[colIndex] ?? ''}
                        onChange={(e) => updateCell(rowIndex, colIndex, e.target.value)}
                        onPaste={(e) => handlePaste(e, rowIndex, colIndex)}
                        disabled={disabled}
                        aria-label={`${column}, row ${rowIndex + 1}`}
                        aria-invalid={!!error}
                        title={error}
                        className={cn(
                          'w-full min-w-32 rounded border-0 bg-transparent px-2 py-1.5 text-sm text-foreground',
                          'focus:outline-none focus:ring-1 focus:ring-ring',
                          'disabled:cursor-not-allowed disabled:opacity-50',
                          error && 'bg-destructive/10 ring-1 ring-destructive/50'
                        )}
                      />
                    </td>
                  )
                })}
                <td className="p-0 text-center">
                  <button
                    type="button"
                    onClick={() => removeRow(rowIndex)}
                    disabled={disabled || rows.length <= 1}
                    aria-label={`Remove row ${rowIndex + 1}`}
                    className="rounded p-1 text-muted-foreground hover:text-destructive disabled:pointer-events-none disabled:opacity-30"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="border-t border-border p-2">
        <Button type="button" variant="outline" size="sm" onClick={addRow} disabled={disabled}>
          <Plus className="h-4 w-4" /> Add row
        </Button>
      </div>
    </div>
  )
}
