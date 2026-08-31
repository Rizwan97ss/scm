/** Shared shape every bulk-import endpoint returns (students, staff, exam marks, questions, ...). */
export interface ImportResult {
  imported_count: number
  /** Only meaningful for the entities that support update/upsert mode (departments, grade levels, sections, subjects, rooms) — 0 otherwise. */
  updated_count?: number
  failed_count: number
  failures: { row: number; attribute: string; errors: string[] }[]
  /** Non-blocking — the row still imported. Currently only students import populates this (possible-duplicate detection by name+DOB); absent/empty for every other entity. */
  warnings?: { row: number; message: string }[]
  /** true when nothing was actually written — see ImportForm's preview → confirm flow. */
  dry_run: boolean
  /** Only students import can return this — a large-enough real commit (see StudentImportController::ASYNC_THRESHOLD_BYTES) is handed to the queue worker instead of run inline; every other field above is meaningless (not yet known) when this is true. ImportForm polls import_log_id until it's done — see ProcessStudentImportJob. */
  queued?: boolean
  import_log_id?: number
}

/** create: rejects an existing match. update: only touches an existing match, fails if none. upsert: create-or-update. Only supported by the lookup-table imports (departments/grade-levels/sections/subjects/rooms) — students/staff imports are always 'create'. */
export type ImportMode = 'create' | 'update' | 'upsert'

/** Every import prior to the queued-students-import feature ran synchronously and is logged already-finished, as 'completed' — only a large-file student import actually passes through 'queued'/'processing'. */
export type ImportLogStatus = 'queued' | 'processing' | 'completed' | 'failed'

export interface ImportLog {
  id: number
  entity: string
  performed_by: { id: number; full_name: string } | null
  file_name: string
  mode: string
  dry_run: boolean
  status: ImportLogStatus
  failure_reason: string | null
  created_count: number
  updated_count: number
  failed_count: number
  failures: { row: number; attribute: string; errors: string[] }[]
  warnings: { row: number; message: string }[]
  undone_at: string | null
  /** Structural eligibility only (right entity, not a dry run, not already undone, something was created) — still gated behind the audit-logs.manage permission client-side, same as the server does. */
  can_undo: boolean
  created_at: string
}

export interface ImportUndoResult {
  deleted: number
  blocked: { type: string; id: number; label: string }[]
}
