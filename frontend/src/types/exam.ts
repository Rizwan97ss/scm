export interface GradeBand {
  id: number
  min_percentage: number
  max_percentage: number
  grade_label: string
  grade_point: number | null
  remark: string | null
}

export interface GradeBandInput {
  min_percentage: number
  max_percentage: number
  grade_label: string
  grade_point?: number | null
  remark?: string | null
}

export interface GradingScale {
  id: number
  name: string
  is_default: boolean
  grade_bands: GradeBand[]
  created_at: string
}

export interface GradingScalePayload {
  name: string
  is_default?: boolean
  grade_bands: GradeBandInput[]
}

export interface ExamType {
  id: number
  name: string
  code: string
  sequence: number
  is_active: boolean
}

export interface ExamTypePayload {
  name: string
  code: string
  sequence?: number
  is_active?: boolean
}

export interface AssessmentComponentType {
  id: number
  name: string
  code: string
  is_auto_graded: boolean
  sequence: number
  is_active: boolean
}

export interface AssessmentComponentTypePayload {
  name: string
  code: string
  is_auto_graded?: boolean
  sequence?: number
  is_active?: boolean
}

/** One gradable component (Online MCQ, Written, ...) under an ExamSubjectGroup — was "the whole subject" before Phase 16. */
export interface ExamSubject {
  id: number
  exam_id: number
  exam_subject_group_id: number
  assessment_component_type: { id: number; name: string; is_auto_graded: boolean } | null
  sequence: number
  max_marks: number
  exam_date: string | null
  is_online: boolean
  duration_minutes: number | null
  online_starts_at: string | null
  online_ends_at: string | null
  shuffle_questions: boolean
  max_attempts: number
}

export interface ExamSubjectComponentInput {
  assessment_component_type_id: number
  max_marks: number
  sequence?: number
  exam_date?: string | null
  is_online?: boolean
  duration_minutes?: number | null
  online_starts_at?: string | null
  online_ends_at?: string | null
  shuffle_questions?: boolean
  max_attempts?: number
}

/** One subject-in-section-in-exam — the "unified result" grouping of 1+ components. */
export interface ExamSubjectGroup {
  id: number
  exam_id: number
  subject?: { id: number; name: string }
  section?: { id: number; name: string }
  grading_scale_id: number | null
  passing_marks: number | null
  published_at: string | null
  components: ExamSubject[]
}

export interface ExamSubjectGroupInput {
  subject_id: number
  section_id: number
  grading_scale_id?: number | null
  passing_marks?: number | null
  components: ExamSubjectComponentInput[]
}

export interface Exam {
  id: number
  academic_year_id: number
  term_id: number | null
  exam_type: { id: number; name: string } | null
  name: string
  weight: number
  is_published: boolean
  published_at: string | null
  exam_subject_groups: ExamSubjectGroup[]
  created_at: string
}

export interface ExamPayload {
  academic_year_id: number
  term_id?: number | null
  exam_type_id?: number | null
  name: string
  weight?: number
  exam_subject_groups?: ExamSubjectGroupInput[]
}

export interface ExamMark {
  id: number
  exam_subject_id: number
  student_id: number
  student?: { id: number; full_name: string; admission_number: string }
  marks_obtained: number | null
  is_absent: boolean
  percentage: number | null
  remarks: string | null
  entered_by?: { id: number; full_name: string } | null
  updated_at: string
}

export interface MarkExamEntry {
  student_id: number
  marks_obtained?: number | null
  is_absent?: boolean
  remarks?: string | null
}

export type SubjectResultStatus = 'draft' | 'calculated' | 'published'

export interface SubjectResultComponentRow {
  id: number
  type: string | null
  max_marks: number
  marks_obtained: number | null
  is_absent: boolean
}

/** One subject's combined result — the same shape whether previewed by staff or shown to a Student/Parent post-publish; masked to nulls (except `group.status`) pre-publish. */
export interface SubjectResultRow {
  group: {
    id: number
    subject: { id: number; name: string }
    section: { id: number; name: string }
    status: SubjectResultStatus
    passing_marks: number | null
  }
  components: SubjectResultComponentRow[]
  max_marks_total: number
  marks_obtained_total: number | null
  is_absent: boolean
  percentage: number | null
  grade_label: string | null
  grade_point: number | null
  remark: string | null
  is_pass: boolean | null
}

export interface ReportCard {
  student: { id: number; full_name: string; admission_number: string }
  exam: { id: number; name: string; is_published: boolean }
  subjects: SubjectResultRow[]
  overall_percentage: number | null
  overall_gpa: number | null
}

export interface TermResultExamRow {
  exam: { id: number; name: string }
  weight: number
  percentage: number | null
  gpa: number | null
}

export interface TermResult {
  student: { id: number; full_name: string; admission_number: string }
  term: { id: number; name: string }
  exams: TermResultExamRow[]
  weighted_percentage: number | null
  weighted_gpa: number | null
  grade_label: string | null
  rank: { position: number; out_of: number } | null
}

export const QUESTION_TYPES = ['mcq', 'true_false'] as const
export type QuestionType = (typeof QUESTION_TYPES)[number]
export const QUESTION_TYPE_LABELS: Record<QuestionType, string> = {
  mcq: 'Multiple Choice',
  true_false: 'True / False',
}

export interface QuestionOption {
  id: number
  option_text: string
  is_correct: boolean
  sequence: number
}

export interface QuestionOptionInput {
  option_text: string
  is_correct?: boolean
}

export interface Question {
  id: number
  subject: { id: number; name: string } | null
  type: QuestionType
  text: string
  default_marks: number
  negative_marks: number | null
  explanation: string | null
  options: QuestionOption[]
  created_at: string
}

export interface QuestionPayload {
  subject_id?: number | null
  exam_subject_id?: number | null
  type: QuestionType
  text: string
  default_marks?: number
  negative_marks?: number | null
  explanation?: string | null
  options: QuestionOptionInput[]
}

export interface QuestionImportResult {
  imported_count: number
  failed_count: number
  failures: { row: number; attribute: string; errors: string[] }[]
}

export interface ExamMarkImportResult {
  imported_count: number
  failed_count: number
  failures: { row: number; attribute: string; errors: string[] }[]
}

export interface SyncOnlineTestQuestionsPayload {
  questions: { question_id: number; marks?: number | null }[]
}

/** Sanitized for a student mid-attempt — never carries the answer key. */
export interface TestQuestion {
  question_id: number
  type: QuestionType
  text: string
  marks: number
  options: { id: number; option_text: string }[]
}

export const ATTEMPT_STATUSES = ['in_progress', 'submitted'] as const
export type AttemptStatus = (typeof ATTEMPT_STATUSES)[number]

export interface OnlineTestAnswerReview {
  question_id: number
  question_text: string
  selected_option_id: number | null
  correct_option_id: number | null
  is_correct: boolean | null
  marks_awarded: number | null
  explanation: string | null
}

export interface OnlineTestAttempt {
  id: number
  exam_subject_id: number
  student_id: number
  attempt_number: number
  status: AttemptStatus
  started_at: string
  submitted_at: string | null
  // Absent entirely (not just null) until the subject's result is declared —
  // see OnlineTestAttemptResource's masking. A masked response is not the
  // same as "no attempt exists," so this is `?:` rather than `| null`.
  score?: number | null
  max_score?: number | null
  answers?: OnlineTestAnswerReview[]
}

export interface StartAttemptResponse {
  attempt: OnlineTestAttempt
  duration_minutes: number | null
  questions: TestQuestion[]
}

export interface MyOnlineTestRow {
  exam_subject_id: number
  exam_name: string
  subject_name: string
  duration_minutes: number | null
  online_starts_at: string | null
  online_ends_at: string | null
  max_attempts: number
  attempts_used: number
  result_declared: boolean
  best_score: number | null
  max_score: number | null
}
