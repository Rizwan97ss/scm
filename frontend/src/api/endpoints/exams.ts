import { httpClient } from '@/api/client'
import { createCrudEndpoints } from './crudFactory'
import { apiFileUrl } from '@/utils/apiFileUrl'
import type { ApiResponse } from '@/types/api'
import type {
  Exam,
  ExamMark,
  ExamPayload,
  GradingScale,
  GradingScalePayload,
  MarkExamEntry,
  MyOnlineTestRow,
  Question,
  QuestionPayload,
  ReportCard,
  StartAttemptResponse,
  SyncOnlineTestQuestionsPayload,
  TermResult,
  OnlineTestAttempt,
} from '@/types/exam'

export const gradingScalesApi = createCrudEndpoints<GradingScale, GradingScalePayload>('grading-scales')
export const questionsApi = createCrudEndpoints<Question, QuestionPayload>('questions')

export const examsApi = {
  ...createCrudEndpoints<Exam, ExamPayload>('exams'),
  destroyExamSubject: async (examId: number, examSubjectId: number): Promise<void> => {
    await httpClient.delete(`/exams/${examId}/exam-subjects/${examSubjectId}`)
  },
  publish: async (id: number): Promise<Exam> => {
    const { data } = await httpClient.post<ApiResponse<Exam>>(`/exams/${id}/publish`)
    return data.data
  },
  unpublish: async (id: number): Promise<Exam> => {
    const { data } = await httpClient.post<ApiResponse<Exam>>(`/exams/${id}/unpublish`)
    return data.data
  },
  reportCard: async (examId: number, studentId: number): Promise<ReportCard> => {
    const { data } = await httpClient.get<ApiResponse<ReportCard>>(`/exams/${examId}/report-card`, { params: { student_id: studentId } })
    return data.data
  },
  reportCardPdfUrl: (examId: number, studentId: number) => apiFileUrl(`/exams/${examId}/report-card/pdf?student_id=${studentId}`),
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
}

export const termResultsApi = {
  get: async (termId: number, studentId: number): Promise<TermResult> => {
    const { data } = await httpClient.get<ApiResponse<TermResult>>(`/terms/${termId}/result`, { params: { student_id: studentId } })
    return data.data
  },
}

export const onlineTestsApi = {
  mine: async (): Promise<MyOnlineTestRow[]> => {
    const { data } = await httpClient.get<ApiResponse<MyOnlineTestRow[]>>('/online-tests/mine')
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
}
