import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse } from '@/types/api'
import type {
  AssessmentComponentType,
  AssessmentComponentTypePayload,
  Exam,
  ExamMark,
  ExamMarkImportResult,
  ExamPayload,
  ExamSubjectGroup,
  ExamTimetable,
  ExamTimetableItemInput,
  ExamType,
  ExamTypePayload,
  GradingScale,
  GradingScalePayload,
  MarkExamEntry,
  MyOnlineTestRow,
  OnlineTestStatus,
  Question,
  QuestionImportResult,
  QuestionPayload,
  ReportCard,
  StartAttemptResponse,
  SubjectResultRow,
  SyncOnlineTestQuestionsPayload,
  TermResult,
  ViolationEventType,
  OnlineTestAttempt,
} from '@/types/exam'

export const gradingScalesApi = createCrudEndpoints<GradingScale, GradingScalePayload>('grading-scales')
export const examTypesApi = createCrudEndpoints<ExamType, ExamTypePayload>('exam-types')
export const assessmentComponentTypesApi = createCrudEndpoints<AssessmentComponentType, AssessmentComponentTypePayload>('assessment-component-types')

export const questionsApi = {
  ...createCrudEndpoints<Question, QuestionPayload>('questions'),
  importTemplateUrl: (): string => apiFileUrl('/questions/import/template'),
  import: async (file: File, subjectId: number, examSubjectId?: number): Promise<QuestionImportResult> => {
    const form = new FormData()
    form.append('file', file)
    form.append('subject_id', String(subjectId))
    if (examSubjectId) form.append('exam_subject_id', String(examSubjectId))
    const { data } = await httpClient.post<ApiResponse<QuestionImportResult>>('/questions/import', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}

export const examsApi = {
  ...createCrudEndpoints<Exam, ExamPayload>('exams'),
  destroyComponent: async (examId: number, groupId: number, examSubjectId: number): Promise<void> => {
    await httpClient.delete(`/exams/${examId}/exam-subject-groups/${groupId}/components/${examSubjectId}`)
  },
  publish: async (id: number): Promise<Exam> => {
    const { data } = await httpClient.post<ApiResponse<Exam>>(`/exams/${id}/publish`)
    return data.data
  },
  unpublish: async (id: number): Promise<Exam> => {
    const { data } = await httpClient.post<ApiResponse<Exam>>(`/exams/${id}/unpublish`)
    return data.data
  },
  publishGroup: async (examId: number, groupId: number): Promise<ExamSubjectGroup> => {
    const { data } = await httpClient.post<ApiResponse<ExamSubjectGroup>>(`/exams/${examId}/exam-subject-groups/${groupId}/publish`)
    return data.data
  },
  unpublishGroup: async (examId: number, groupId: number): Promise<ExamSubjectGroup> => {
    const { data } = await httpClient.post<ApiResponse<ExamSubjectGroup>>(`/exams/${examId}/exam-subject-groups/${groupId}/unpublish`)
    return data.data
  },
  groupResult: async (examId: number, groupId: number, studentId: number): Promise<SubjectResultRow> => {
    const { data } = await httpClient.get<ApiResponse<SubjectResultRow>>(`/exams/${examId}/exam-subject-groups/${groupId}/result`, { params: { student_id: studentId } })
    return data.data
  },
  reportCard: async (examId: number, studentId: number): Promise<ReportCard> => {
    const { data } = await httpClient.get<ApiResponse<ReportCard>>(`/exams/${examId}/report-card`, { params: { student_id: studentId } })
    return data.data
  },
  reportCardPdfUrl: (examId: number, studentId: number) => apiFileUrl(`/exams/${examId}/report-card/pdf?student_id=${studentId}`),
  timetable: async (examId: number, params: { sectionId?: number; studentId?: number }): Promise<ExamTimetable> => {
    const { data } = await httpClient.get<ApiResponse<ExamTimetable>>(`/exams/${examId}/timetable`, {
      params: { section_id: params.sectionId, student_id: params.studentId },
    })
    return data.data
  },
  saveTimetable: async (examId: number, sectionId: number, items: ExamTimetableItemInput[]): Promise<ExamTimetable> => {
    const { data } = await httpClient.put<ApiResponse<ExamTimetable>>(`/exams/${examId}/timetable`, { section_id: sectionId, items })
    return data.data
  },
}

export const examMarksApi = {
  list: async (examSubjectId: number): Promise<ExamMark[]> => {
    const { data } = await httpClient.get<ApiResponse<ExamMark[]>>(`/exam-subjects/${examSubjectId}/marks`)
    return data.data
  },
  mark: async (examSubjectId: number, entries: MarkExamEntry[]): Promise<ExamMark[]> => {
    const { data } = await httpClient.post<ApiResponse<ExamMark[]>>(`/exam-subjects/${examSubjectId}/marks`, { entries })
    return data.data
  },
  correct: async (examMarkId: number, payload: { marks_obtained?: number | null; is_absent?: boolean; remarks?: string | null }): Promise<ExamMark> => {
    const { data } = await httpClient.put<ApiResponse<ExamMark>>(`/exam-marks/${examMarkId}`, payload)
    return data.data
  },
  importTemplateUrl: (examSubjectId: number): string => apiFileUrl(`/exam-subjects/${examSubjectId}/marks/import/template`),
  import: async (examSubjectId: number, file: File): Promise<ExamMarkImportResult> => {
    const form = new FormData()
    form.append('file', file)
    const { data } = await httpClient.post<ApiResponse<ExamMarkImportResult>>(`/exam-subjects/${examSubjectId}/marks/import`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return data.data
  },
}

export const termResultsApi = {
  get: async (termId: number, studentId: number): Promise<TermResult> => {
    const { data } = await httpClient.get<ApiResponse<TermResult>>(`/terms/${termId}/result`, { params: { student_id: studentId } })
    return data.data
  },
  pdfUrl: (termId: number, studentId: number) => apiFileUrl(`/terms/${termId}/result/pdf?student_id=${studentId}`),
}

export const onlineTestsApi = {
  mine: async (): Promise<MyOnlineTestRow[]> => {
    const { data } = await httpClient.get<ApiResponse<MyOnlineTestRow[]>>('/online-tests/mine')
    return data.data
  },
  questions: async (examSubjectId: number): Promise<Question[]> => {
    const { data } = await httpClient.get<ApiResponse<Question[]>>(`/exam-subjects/${examSubjectId}/online-test-questions`)
    return data.data
  },
  syncQuestions: async (examSubjectId: number, payload: SyncOnlineTestQuestionsPayload): Promise<void> => {
    await httpClient.post(`/exam-subjects/${examSubjectId}/online-test-questions`, payload)
  },
  start: async (examSubjectId: number): Promise<StartAttemptResponse> => {
    const { data } = await httpClient.post<ApiResponse<StartAttemptResponse>>(`/exam-subjects/${examSubjectId}/attempts`)
    return data.data
  },
  saveAnswer: async (attemptId: number, questionId: number, selectedOptionId: number | null): Promise<void> => {
    await httpClient.put(`/online-test-attempts/${attemptId}/answers`, { question_id: questionId, selected_option_id: selectedOptionId })
  },
  submit: async (attemptId: number): Promise<OnlineTestAttempt> => {
    const { data } = await httpClient.post<ApiResponse<OnlineTestAttempt>>(`/online-test-attempts/${attemptId}/submit`)
    return data.data
  },
  getAttempt: async (attemptId: number): Promise<OnlineTestAttempt> => {
    const { data } = await httpClient.get<ApiResponse<OnlineTestAttempt>>(`/online-test-attempts/${attemptId}`)
    return data.data
  },
  onlineStatus: async (examSubjectId: number): Promise<OnlineTestStatus> => {
    const { data } = await httpClient.get<ApiResponse<OnlineTestStatus>>(`/exam-subjects/${examSubjectId}/online-status`)
    return data.data
  },
  reportViolation: async (attemptId: number, eventType: ViolationEventType): Promise<OnlineTestAttempt> => {
    const { data } = await httpClient.post<ApiResponse<OnlineTestAttempt>>(`/online-test-attempts/${attemptId}/violations`, { event_type: eventType })
    return data.data
  },
  heartbeat: async (attemptId: number): Promise<void> => {
    await httpClient.post(`/online-test-attempts/${attemptId}/heartbeat`)
  },
}
