import type { QuestionType } from '@/types/exam'

export const QUESTION_TYPE_TRANSLATION_KEY: Record<QuestionType, string> = {
  mcq: 'exams.multipleChoice',
  true_false: 'exams.trueFalse',
}
