import { lazy, Suspense, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Download, Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Badge } from './Badge'
import { Button } from './Button'
import { FileUpload } from './FileUpload'
import { FormField } from './FormField'
import { Select } from './Select'
import { downloadFile } from '@/utils/download'
import { rowsToCsvFile } from '@/utils/csv'
import { formatApiError, type ApiError } from '@/api/client'
import { importLogsApi } from '@/api/endpoints/importLogs'
import type { ImportLog, ImportMode, ImportResult } from '@/types/import'
import { cn } from '@/utils/cn'

/**
 * Every OTHER import page (7 of 8) never passes `columns`, so neither of
 * these should cost them anything on first load — lazy, not a static
 * import, and only requested once a `columns`-using page actually renders.
 * See DepartmentImportPage for the one page that opts in so far.
 *
 * Both share the SAME dynamic import() call (one module, `GridInputModes`)
 * rather than each getting its own `lazy()`/import() pair — splitting them
 * into two separate chunks measurably regressed the *main* bundle instead
 * of shrinking it: a new async chunk statically reaching a widely-shared
 * component (PasteGrid → Tooltip, then separately ImportFileMapper →
 * Select) was enough, twice, to flip Rolldown's chunking heuristic into
 * folding unrelated large chunks (Tooltip, AuthContext) into the main
 * entry instead of keeping them separate. One shared chunk boundary here
 * avoided it — see docs/testing.md's Rolldown chunking gotcha.
 */
let gridInputModesPromise: Promise<typeof import('./GridInputModes')> | null = null
function loadGridInputModes() {
  gridInputModesPromise ??= import('./GridInputModes')
  return gridInputModesPromise
}
const PasteGrid = lazy(() => loadGridInputModes().then((m) => ({ default: m.PasteGrid })))
const ImportFileMapper = lazy(() => loadGridInputModes().then((m) => ({ default: m.ImportFileMapper })))

/** Reshapes a polled ImportLog into the same ImportResult ImportForm already knows how to render, once a queued import finishes — see ProcessStudentImportJob. */
function logToResult(log: ImportLog): ImportResult {
  return {
    imported_count: log.created_count,
    updated_count: log.updated_count,
    failed_count: log.failed_count,
    failures: log.failures,
    warnings: log.warnings,
    dry_run: false,
  }
}

function getModeOptions(t: TFunction): { value: ImportMode; label: string }[] {
  return [
    { value: 'create', label: t('importForm.modeCreate') },
    { value: 'update', label: t('importForm.modeUpdate') },
    { value: 'upsert', label: t('importForm.modeUpsert') },
  ]
}

interface ImportFormProps {
  /** Used in toasts/headings, e.g. "student" → "3 student(s) imported." */
  entityLabel: string
  templateUrl: string
  templateFilename: string
  description: ReactNode
  onImport: (file: File, dryRun: boolean, mode: ImportMode) => Promise<ImportResult>
  /** Only the LookupImportController-backed imports (departments/grade-levels/sections/subjects/rooms) support update/upsert — students/staff imports are always 'create', so this defaults to hiding the selector. */
  supportsMode?: boolean
  /**
   * Column headers, in order — exactly matching the backend import's
   * expected field names (see each entity's *ImportTemplateExport). When
   * given, two alternate tabs appear alongside file upload: "Paste data" —
   * an editable grid the user can type or paste spreadsheet rows into
   * directly — and "Upload & map columns" — a file with whatever headers it
   * already has, matched to these canonical columns via ImportFileMapper.
   * Both converge into the same grid review step, validated and committed
   * through the exact same onImport call (converted to the same CSV shape a
   * file upload would produce), with failures highlighted on the offending
   * cell for inline correction — not just listed below like a file upload's
   * FailureTable.
   */
  columns?: string[]
}

function FailureTable({ failures }: { failures: ImportResult['failures'] }) {
  const { t } = useTranslation()
  if (failures.length === 0) return null

  return (
    <div className="rounded-md border border-border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
          <tr>
            <th className="p-2 text-start">{t('importForm.columnRow')}</th>
            <th className="p-2 text-start">{t('importForm.columnField')}</th>
            <th className="p-2 text-start">{t('importForm.columnError')}</th>
          </tr>
        </thead>
        <tbody>
          {failures.map((failure, index) => (
            <tr key={index} className="border-t border-border">
              <td className="p-2">{failure.row}</td>
              <td className="p-2">{failure.attribute}</td>
              <td className="p-2">{failure.errors.join(', ')}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function ResultBadges({ result, verb }: { result: ImportResult; verb: 'would be' | '' }) {
  const { t } = useTranslation()
  const createdLabel = verb ? t('importForm.resultWouldBeCreated') : t('importForm.resultCreated')
  const updatedLabel = verb ? t('importForm.resultWouldBeUpdated') : t('importForm.resultUpdated')

  return (
    <div className="flex flex-wrap gap-2">
      {result.imported_count > 0 && <Badge variant="success">{result.imported_count} {createdLabel}</Badge>}
      {!!result.updated_count && result.updated_count > 0 && <Badge variant="info">{result.updated_count} {updatedLabel}</Badge>}
      {result.imported_count === 0 && !result.updated_count && (
        <Badge variant="default">{verb ? t('importForm.resultNothingToImport') : t('importForm.resultNothingToReport')}</Badge>
      )}
      {!!result.warnings?.length && <Badge variant="warning">{t('importForm.resultPossibleDuplicate', { count: result.warnings.length })}</Badge>}
      {result.failed_count > 0 && <Badge variant="destructive">{result.failed_count} {verb ? t('importForm.resultWouldFail') : t('importForm.resultFailed')}</Badge>}
    </div>
  )
}

/**
 * Distinct from FailureTable on purpose — a warning never blocked the row
 * from importing (see StudentsImport::checkForPossibleDuplicate()'s "never
 * auto-merge, just flag for review" rule), so it reads as advisory, not an
 * error.
 */
function WarningList({ warnings }: { warnings: ImportResult['warnings'] }) {
  const { t } = useTranslation()
  if (!warnings?.length) return null

  return (
    <div className="rounded-md border border-warning/40 bg-warning/5 p-3">
      <p className="mb-2 text-sm font-medium text-warning">{t('importForm.reviewBeforeNewRecords')}</p>
      <ul className="flex flex-col gap-1.5 text-sm">
        {warnings.map((warning, index) => (
          <li key={index}>
            <span className="font-medium">{t('importForm.rowLabel', { row: warning.row })}</span> {warning.message}
          </li>
        ))}
      </ul>
    </div>
  )
}

interface FileModeSectionProps {
  selectedFile: File | null
  onFileSelect: (file: File) => void
  onClear: () => void
  disabled: boolean
  preview: ImportResult | null
  previewHasNothingToCommit: boolean
  previewPending: boolean
  commitPending: boolean
  onPreview: () => void
  onCommit: () => void
}

/** The file-upload input → preview → confirm flow — identical whether it's the only input option (no `columns`) or one tab of two (grid mode also available). */
function FileModeSection({
  selectedFile,
  onFileSelect,
  onClear,
  disabled,
  preview,
  previewHasNothingToCommit,
  previewPending,
  commitPending,
  onPreview,
  onCommit,
}: FileModeSectionProps) {
  const { t } = useTranslation()
  return (
    <>
      <FileUpload accept=".xlsx,.xls,.csv" selectedFileName={selectedFile?.name} onFileSelect={onFileSelect} onClear={onClear} disabled={disabled} />

      {selectedFile && !preview && (
        <Button className="mt-4" onClick={onPreview} isLoading={previewPending}>
          {t('importForm.previewImport')}
        </Button>
      )}

      {preview && selectedFile && (
        <div className="mt-6 flex flex-col gap-3">
          <p className="text-sm font-medium">{t('importForm.nothingSavedYet')}</p>
          <ResultBadges result={preview} verb="would be" />
          <WarningList warnings={preview.warnings} />
          <FailureTable failures={preview.failures} />
          <div className="flex gap-2">
            <Button onClick={onCommit} isLoading={commitPending} disabled={previewHasNothingToCommit}>
              {t('importForm.confirmImport')}
            </Button>
            <Button variant="outline" onClick={onClear} disabled={commitPending}>
              {t('importForm.chooseDifferentFile')}
            </Button>
          </div>
        </div>
      )}
    </>
  )
}

/**
 * Shared upload → preview → confirm UI for every bulk-import feature
 * (students, staff, ...). Nothing is written to the database until the user
 * has seen a preview (every row validated, same checks the real import
 * runs, including permission/business-rule ones — see each backend Import
 * class's dryRun handling) and explicitly confirms it — a file upload used
 * to commit immediately with no chance to review it first.
 */
export function ImportForm({ entityLabel, templateUrl, templateFilename, description, onImport, supportsMode = false, columns }: ImportFormProps) {
  const { t } = useTranslation()
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [inputMode, setInputMode] = useState<'file' | 'grid' | 'map'>('file')
  const [gridRows, setGridRows] = useState<string[][]>(() => [columns?.map(() => '') ?? []])
  const [mode, setMode] = useState<ImportMode>('create')
  const [preview, setPreview] = useState<ImportResult | null>(null)
  const [committed, setCommitted] = useState<ImportResult | null>(null)
  const [queuedLogId, setQueuedLogId] = useState<number | null>(null)

  const usingGrid = !!columns && inputMode === 'grid'
  const activeFile = usingGrid ? rowsToCsvFile(columns!, gridRows, 'grid-import.csv') : selectedFile
  const hasGridContent = gridRows.some((row) => row.some((cell) => cell.trim() !== ''))
  const canPreview = usingGrid ? hasGridContent : !!selectedFile

  const cellErrors = useMemo(() => {
    if (!usingGrid || !preview) return undefined
    const map = new Map<string, string>()
    preview.failures.forEach((failure) => map.set(`${failure.row - 2}:${failure.attribute}`, failure.errors.join(', ')))
    return map
    // Row numbers are 1-indexed including the header row (PhpSpreadsheet convention) — row 2 is gridRows[0].
  }, [usingGrid, preview])

  const previewMutation = useMutation({
    mutationFn: (file: File) => onImport(file, true, mode),
    onSuccess: setPreview,
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  const commitMutation = useMutation({
    mutationFn: (file: File) => onImport(file, false, mode),
    onSuccess: (data) => {
      if (data.queued && data.import_log_id) {
        setQueuedLogId(data.import_log_id)
        toast.message(t('importForm.queuedInBackground'))
        return
      }
      setCommitted(data)
      const parts = [
        data.imported_count > 0 && `${data.imported_count} ${t('importForm.resultCreated')}`,
        !!data.updated_count && `${data.updated_count} ${t('importForm.resultUpdated')}`,
      ].filter(Boolean)
      toast.success(t('importForm.entitySummary', { entity: entityLabel, summary: parts.join(', ') || t('importForm.nothingChanged') }))
    },
    onError: (error) => toast.error(formatApiError(error as ApiError)),
  })

  const queuedLog = useQuery({
    queryKey: ['import-logs', queuedLogId],
    queryFn: () => importLogsApi.get(queuedLogId!),
    enabled: queuedLogId !== null,
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status === 'queued' || status === 'processing' ? 2000 : false
    },
  })

  useEffect(() => {
    const log = queuedLog.data
    if (!log || (log.status !== 'completed' && log.status !== 'failed')) return

    setQueuedLogId(null)
    if (log.status === 'failed') {
      toast.error(log.failure_reason ?? t('importForm.backgroundImportFailed'))
    } else {
      setCommitted(logToResult(log))
      const summary = `${log.created_count} ${t('importForm.resultCreated')}${log.failed_count ? `, ${log.failed_count} ${t('importForm.resultFailed')}` : ''}`
      toast.success(t('importForm.entitySummary', { entity: entityLabel, summary }))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [queuedLog.data?.status])

  function reset() {
    setSelectedFile(null)
    setGridRows([columns?.map(() => '') ?? []])
    setPreview(null)
    setCommitted(null)
    setQueuedLogId(null)
  }

  const previewHasNothingToCommit = preview !== null && preview.imported_count === 0 && !preview.updated_count
  const busy = previewMutation.isPending || commitMutation.isPending

  return (
    <div className={usingGrid ? 'max-w-4xl' : 'max-w-xl'}>
      <div className="mb-4 flex justify-end">
        <Button variant="outline" onClick={() => downloadFile(templateUrl, templateFilename)}>
          <Download className="h-4 w-4" /> {t('importForm.downloadTemplate')}
        </Button>
      </div>

      <p className="mb-4 text-sm text-muted-foreground">{description}</p>

      {supportsMode && !committed && !queuedLogId && (
        <FormField label={t('importForm.modeLabel')} htmlFor="import-mode" className="mb-4">
          <Select
            id="import-mode"
            value={mode}
            onValueChange={(value) => setMode(value as ImportMode)}
            options={getModeOptions(t)}
            disabled={busy}
          />
        </FormField>
      )}

      {columns && !committed && !queuedLogId && (
        <div>
          <div className="mb-4 inline-flex rounded-md border border-border p-0.5">
            {(['file', 'grid', 'map'] as const).map((option) => (
              <button
                key={option}
                type="button"
                onClick={() => setInputMode(option)}
                className={cn(
                  'rounded px-3 py-1.5 text-sm font-medium transition-colors',
                  inputMode === option ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                {option === 'file' ? t('importForm.tabUploadFile') : option === 'grid' ? t('importForm.tabPasteData') : t('importForm.tabUploadMap')}
              </button>
            ))}
          </div>

          {inputMode === 'file' && (
            <FileModeSection
              selectedFile={selectedFile}
              onFileSelect={(file) => {
                setSelectedFile(file)
                setPreview(null)
              }}
              onClear={reset}
              disabled={busy}
              preview={preview}
              previewHasNothingToCommit={previewHasNothingToCommit}
              previewPending={previewMutation.isPending}
              commitPending={commitMutation.isPending}
              onPreview={() => previewMutation.mutate(activeFile!)}
              onCommit={() => commitMutation.mutate(activeFile!)}
            />
          )}

          {inputMode === 'grid' && (
            <div className="flex flex-col gap-3">
              <p className="text-sm text-muted-foreground">{t('importForm.gridInstructions')}</p>
              <Suspense fallback={<div className="rounded-md border border-border p-4 text-sm text-muted-foreground">{t('importForm.loadingGrid')}</div>}>
                <PasteGrid columns={columns} rows={gridRows} onRowsChange={setGridRows} cellErrors={cellErrors} disabled={busy} />
              </Suspense>
              <div className="flex flex-wrap items-center gap-2">
                <Button onClick={() => previewMutation.mutate(activeFile!)} isLoading={previewMutation.isPending} disabled={!canPreview}>
                  {preview ? t('importForm.recheck') : t('importForm.previewImport')}
                </Button>
                {preview && (
                  <Button
                    variant="outline"
                    onClick={() => commitMutation.mutate(activeFile!)}
                    isLoading={commitMutation.isPending}
                    disabled={previewHasNothingToCommit}
                  >
                    {t('importForm.confirmImport')}
                  </Button>
                )}
              </div>
              {preview && (
                <div className="flex flex-col gap-3">
                  <ResultBadges result={preview} verb="would be" />
                  <WarningList warnings={preview.warnings} />
                </div>
              )}
            </div>
          )}

          {inputMode === 'map' && (
            <Suspense fallback={<div className="rounded-md border border-border p-4 text-sm text-muted-foreground">{t('importForm.loading')}</div>}>
              <ImportFileMapper
                columns={columns}
                disabled={busy}
                onMapped={(rows) => {
                  setGridRows(rows)
                  setPreview(null)
                  setInputMode('grid')
                }}
              />
            </Suspense>
          )}
        </div>
      )}

      {!columns && !committed && !queuedLogId && (
        <FileModeSection
          selectedFile={selectedFile}
          onFileSelect={(file) => {
            setSelectedFile(file)
            setPreview(null)
          }}
          onClear={reset}
          disabled={busy}
          preview={preview}
          previewHasNothingToCommit={previewHasNothingToCommit}
          previewPending={previewMutation.isPending}
          commitPending={commitMutation.isPending}
          onPreview={() => previewMutation.mutate(activeFile!)}
          onCommit={() => commitMutation.mutate(activeFile!)}
        />
      )}

      {queuedLogId && (
        <div className="mt-6 flex items-center gap-3 rounded-md border border-border bg-muted/30 p-4 text-sm">
          <Loader2 className="h-4 w-4 shrink-0 animate-spin text-muted-foreground" aria-hidden="true" />
          <span>{t('importForm.queuedNotice')}</span>
        </div>
      )}

      {committed && (
        <div className="mt-6 flex flex-col gap-3">
          <ResultBadges result={committed} verb="" />
          <WarningList warnings={committed.warnings} />
          <FailureTable failures={committed.failures} />
          <div>
            <Button variant="outline" onClick={reset}>
              {t('importForm.importAnotherFile')}
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
