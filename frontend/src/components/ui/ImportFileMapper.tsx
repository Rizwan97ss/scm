import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'
import { Button } from './Button'
import { FileUpload } from './FileUpload'
import { importsApi, type ImportFilePreview } from '@/api/endpoints/imports'
import { formatApiError, type ApiError } from '@/api/client'
import { guessColumnMapping } from '@/utils/columnMapping'

const SKIP = '__skip__'
const SAMPLE_ROWS = 3

/**
 * A plain native <select>, not the shared Radix-backed Select component —
 * deliberately: a handful of canonical columns plus "don't import" never
 * needs Select's search box, and pulling in that dependency from this
 * lazy-loaded module is exactly the mistake PasteGrid's Tooltip import
 * already made once this session (see docs/testing.md's Rolldown chunking
 * gotcha) — a component reachable from both an eager import (ImportForm's
 * own mode dropdown) and a new lazy chunk repeatedly convinced Rolldown's
 * chunking heuristic to fold large, unrelated chunks into the main bundle.
 */
function MappingSelect({ value, onChange, options, disabled, ariaLabel }: { value: string; onChange: (value: string) => void; options: { value: string; label: string }[]; disabled?: boolean; ariaLabel: string }) {
  return (
    <select
      value={value}
      onChange={(e) => onChange(e.target.value)}
      disabled={disabled}
      aria-label={ariaLabel}
      className="h-9 w-full rounded-md border border-input bg-card px-2 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
    >
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  )
}

export interface ImportFileMapperProps {
  /** Canonical column names, in the order the resulting grid rows should be built in — same list ImportForm/PasteGrid already use. */
  columns: string[]
  /** Called once the user confirms their mapping, with the file's data rows reordered/filtered to match `columns` exactly. */
  onMapped: (rows: string[][]) => void
  disabled?: boolean
}

/**
 * Lets a user upload a file with whatever headers it already has — "Dept
 * Name" instead of the template's exact "name" — map each one to a
 * canonical column, and continue into the same PasteGrid review step a
 * manual paste would. Nothing here is entity-specific: ImportFilePreviewController
 * just echoes back the uploaded file's own headers/rows, unmapped: only the
 * fuzzy suggestion here in the frontend and the user's own confirmation
 * decide which uploaded column becomes which canonical field.
 */
export function ImportFileMapper({ columns, onMapped, disabled }: ImportFileMapperProps) {
  const { t } = useTranslation()
  const [preview, setPreview] = useState<ImportFilePreview | null>(null)
  const [mapping, setMapping] = useState<(string | null)[]>([])

  const previewMutation = useMutation({
    mutationFn: (file: File) => importsApi.previewFile(file),
    onSuccess: (data) => {
      setPreview(data)
      setMapping(guessColumnMapping(data.headers, columns))
    },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  const columnOptions = [{ value: SKIP, label: t('importFileMapper.dontImportColumn') }, ...columns.map((column) => ({ value: column, label: column }))]

  function reset() {
    setPreview(null)
    setMapping([])
  }

  function confirm() {
    if (!preview) return
    const mappedColumnIndexes = columns.map((column) => mapping.findIndex((m) => m === column))
    const rows = preview.rows.map((row) => mappedColumnIndexes.map((sourceIndex) => (sourceIndex === -1 ? '' : (row[sourceIndex] ?? ''))))
    onMapped(rows)
  }

  const mappedCount = mapping.filter((m) => m !== null).length

  if (!preview) {
    return (
      <div className="flex flex-col gap-3">
        <p className="text-sm text-muted-foreground">{t('importFileMapper.uploadPrompt')}</p>
        <FileUpload accept=".xlsx,.xls,.csv" onFileSelect={(file) => previewMutation.mutate(file)} disabled={disabled || previewMutation.isPending} />
        {previewMutation.isPending && <p className="text-sm text-muted-foreground">{t('importFileMapper.readingFile')}</p>}
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm text-muted-foreground">
        {t('importFileMapper.matchPrompt')}
        {preview.truncated && t('importFileMapper.truncatedNotice')}
      </p>

      <div className="overflow-x-auto rounded-md border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
            <tr>
              <th className="p-2 text-start font-medium">{t('importFileMapper.columnYourColumn')}</th>
              <th className="p-2 text-start font-medium">{t('importFileMapper.columnSampleValues')}</th>
              <th className="p-2 text-start font-medium">{t('importFileMapper.columnMapsTo')}</th>
            </tr>
          </thead>
          <tbody>
            {preview.headers.map((header, index) => {
              if (!header.trim()) return null
              const sample = preview.rows
                .slice(0, SAMPLE_ROWS)
                .map((row) => row[index])
                .filter((value) => !!value)
                .join(', ')

              return (
                <tr key={index} className="border-t border-border">
                  <td className="p-2 font-medium">{header}</td>
                  <td className="max-w-xs truncate p-2 text-muted-foreground" title={sample}>
                    {sample || '—'}
                  </td>
                  <td className="p-2">
                    <MappingSelect
                      value={mapping[index] ?? SKIP}
                      onChange={(value) =>
                        setMapping((prev) => {
                          const next = [...prev]
                          next[index] = value === SKIP ? null : value
                          return next
                        })
                      }
                      options={columnOptions}
                      disabled={disabled}
                      ariaLabel={t('importFileMapper.mapsToAriaLabel', { header })}
                    />
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className="flex items-center gap-2">
        <Button onClick={confirm} disabled={disabled || mappedCount === 0}>
          {t('importFileMapper.continueWithMapped', { count: mappedCount })}
        </Button>
        <Button variant="outline" onClick={reset} disabled={disabled}>
          {t('importFileMapper.chooseDifferentFile')}
        </Button>
      </div>
    </div>
  )
}
