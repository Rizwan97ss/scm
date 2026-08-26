export interface HomeworkAttachment {
  id: number
  file_name: string
  size: number
  url: string
}

export const HOMEWORK_SUBMISSION_STATUSES = ['submitted', 'graded'] as const
export type HomeworkSubmissionStatus = (typeof HOMEWORK_SUBMISSION_STATUSES)[number]
export const HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS: Record<HomeworkSubmissionStatus, string> = {
  submitted: 'homework.statusSubmitted',
  graded: 'homework.statusGraded',
}

export interface HomeworkSubmission {
  id: number
  homework_id: number
  student_id: number
  student?: { id: number; full_name: string; admission_number: string }
  status: HomeworkSubmissionStatus
  content: string | null
  submitted_at: string | null
  score: number | null
  feedback: string | null
  graded_at: string | null
  graded_by?: { id: number; full_name: string } | null
  attachments: HomeworkAttachment[]
}

export interface Homework {
  id: number
  academic_year_id: number
  section?: { id: number; name: string }
  subject?: { id: number; name: string }
  teacher?: { id: number; full_name: string }
  title: string
  description: string | null
  due_date: string
  max_score: number | null
  attachments: HomeworkAttachment[]
  /** Only present when the viewer is a Student — their own submission, if any. */
  my_submission: HomeworkSubmission | null
  created_at: string
}

export interface HomeworkPayload {
  academic_year_id: number
  section_id: number
  subject_id: number
  title: string
  description?: string | null
  due_date: string
  max_score?: number | null
}

export interface HomeworkRosterRow {
  student: { id: number; full_name: string; admission_number: string }
  submission: HomeworkSubmission | null
}

export const REMARK_CATEGORIES = ['academic', 'behavioral', 'general'] as const
export type RemarkCategory = (typeof REMARK_CATEGORIES)[number]
export const REMARK_CATEGORY_LABEL_KEYS: Record<RemarkCategory, string> = {
  academic: 'enums.remarkCategoryAcademic',
  behavioral: 'enums.remarkCategoryBehavioral',
  general: 'enums.remarkCategoryGeneral',
}

export interface StudentRemark {
  id: number
  student_id: number
  author?: { id: number; full_name: string }
  category: RemarkCategory
  body: string
  visible_to_guardian: boolean
  created_at: string
}

export interface StudentRemarkPayload {
  student_id: number
  category?: RemarkCategory
  body: string
  visible_to_guardian?: boolean
}
