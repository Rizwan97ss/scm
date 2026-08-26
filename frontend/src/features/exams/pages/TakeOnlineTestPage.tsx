import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, Clock, Send } from 'lucide-react'
import { onlineTestsApi } from '@/api/endpoints/exams'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, EmptyState } from '@/components/ui'
import type { OnlineTestAttempt, TestQuestion } from '@/types/exam'
import type { ApiError } from '@/api/client'

function formatTime(totalSeconds: number): string {
  const minutes = Math.floor(totalSeconds / 60)
  const seconds = totalSeconds % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

export function TakeOnlineTestPage() {
  const { t } = useTranslation()
  const { examSubjectId } = useParams<{ examSubjectId: string }>()
  const id = Number(examSubjectId)

  const [phase, setPhase] = useState<'landing' | 'in-progress' | 'result'>('landing')
  const [attempt, setAttempt] = useState<OnlineTestAttempt | null>(null)
  const [questions, setQuestions] = useState<TestQuestion[]>([])
  const [answers, setAnswers] = useState<Record<number, number>>({})
  const [deadline, setDeadline] = useState<number | null>(null)
  const [remainingSeconds, setRemainingSeconds] = useState<number | null>(null)
  const [result, setResult] = useState<OnlineTestAttempt | null>(null)

  const startMutation = useMutation({
    mutationFn: () => onlineTestsApi.start(id),
    onSuccess: (data) => {
      setAttempt(data.attempt)
      setQuestions(data.questions)
      if (data.duration_minutes) {
        const startedAtMs = new Date(data.attempt.started_at).getTime()
        setDeadline(startedAtMs + data.duration_minutes * 60_000)
      }
      setPhase('in-progress')
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const answerMutation = useMutation({
    mutationFn: ({ questionId, optionId }: { questionId: number; optionId: number }) => onlineTestsApi.saveAnswer(attempt!.id, questionId, optionId),
  })

  const submitMutation = useMutation({
    mutationFn: () => onlineTestsApi.submit(attempt!.id),
    onSuccess: (data) => {
      setResult(data)
      setPhase('result')
      toast.success(t('exams.testSubmittedToast'))
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  // Auto-submits the moment the countdown hits zero — a student who leaves
  // the tab open past the deadline still gets graded on whatever they'd
  // answered, rather than being stuck unable to submit.
  useEffect(() => {
    if (phase !== 'in-progress' || deadline === null) return

    const interval = setInterval(() => {
      const secondsLeft = Math.max(0, Math.round((deadline - Date.now()) / 1000))
      setRemainingSeconds(secondsLeft)
      if (secondsLeft <= 0) {
        clearInterval(interval)
        submitMutation.mutate()
      }
    }, 1000)

    return () => clearInterval(interval)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase, deadline])

  function selectOption(questionId: number, optionId: number) {
    setAnswers((prev) => ({ ...prev, [questionId]: optionId }))
    answerMutation.mutate({ questionId, optionId })
  }

  if (phase === 'landing') {
    return (
      <div className="mx-auto max-w-lg">
        <PageHeader title={t('exams.onlineTest')} />
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
            <AlertTriangle className="h-8 w-8 text-warning" />
            <p className="text-sm text-muted-foreground">
              {t('exams.startTestWarning')}
            </p>
            <Button onClick={() => startMutation.mutate()} isLoading={startMutation.isPending}>
              {t('exams.startTest')}
            </Button>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (phase === 'in-progress') {
    const answeredCount = Object.keys(answers).length
    return (
      <div className="mx-auto max-w-2xl">
        <div className="sticky top-0 z-10 mb-4 flex items-center justify-between rounded-md border border-border bg-card p-3">
          <span className="text-sm text-muted-foreground">
            {t('exams.answeredCount', { answered: answeredCount, total: questions.length })}
          </span>
          {remainingSeconds !== null && (
            <Badge variant={remainingSeconds < 60 ? 'destructive' : 'outline'} className="flex items-center gap-1">
              <Clock className="h-3.5 w-3.5" /> {formatTime(remainingSeconds)}
            </Badge>
          )}
          <Button size="sm" onClick={() => submitMutation.mutate()} isLoading={submitMutation.isPending}>
            <Send className="h-3.5 w-3.5" /> {t('exams.submitTest')}
          </Button>
        </div>

        <div className="flex flex-col gap-4">
          {questions.map((question, index) => (
            <Card key={question.question_id}>
              <CardContent className="pt-4 sm:pt-6">
                <p className="mb-3 font-medium">
                  {index + 1}. {question.text} <span className="text-xs font-normal text-muted-foreground">({t('exams.marksCount', { count: question.marks })})</span>
                </p>
                <div className="flex flex-col gap-2">
                  {question.options.map((option) => (
                    <label key={option.id} className="flex cursor-pointer items-center gap-2 rounded-md border border-border p-2 text-sm hover:bg-muted">
                      <input
                        type="radio"
                        name={`question-${question.question_id}`}
                        checked={answers[question.question_id] === option.id}
                        onChange={() => selectOption(question.question_id, option.id)}
                      />
                      {option.option_text}
                    </label>
                  ))}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="mt-4 flex justify-end">
          <Button onClick={() => submitMutation.mutate()} isLoading={submitMutation.isPending}>
            <Send className="h-4 w-4" /> {t('exams.submitTest')}
          </Button>
        </div>
      </div>
    )
  }

  if (!result) {
    return <EmptyState title={t('exams.noResultAvailable')} />
  }

  // Graded instantly behind the scenes, but not shown until the subject's
  // result is declared — score/max_score/answers are absent from the
  // response entirely until then (see OnlineTestAttemptResource).
  if (result.score === undefined) {
    return (
      <div className="mx-auto max-w-lg">
        <PageHeader title={t('exams.testSubmitted')} />
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
            <CheckCircle2 className="h-8 w-8 text-success" />
            <p className="text-sm text-muted-foreground">
              {t('exams.testSubmittedPendingMessage')}
            </p>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title={t('exams.testSubmitted')} />
      <Card className="mb-6">
        <CardContent className="flex items-center justify-between pt-4 sm:pt-6">
          <div>
            <p className="text-sm text-muted-foreground">{t('exams.score')}</p>
            <p className="text-2xl font-semibold">{result.score} / {result.max_score}</p>
          </div>
          <Badge variant="success">{t('exams.resultDeclared')}</Badge>
        </CardContent>
      </Card>

      <div className="flex flex-col gap-3">
        {(result.answers ?? []).map((answer, index) => (
          <Card key={answer.question_id}>
            <CardContent className="pt-4 sm:pt-6">
              <div className="mb-1 flex items-start justify-between gap-2">
                <p className="font-medium">{index + 1}. {answer.question_text}</p>
                <Badge variant={answer.is_correct ? 'success' : 'destructive'}>{answer.is_correct ? t('exams.correct') : t('exams.incorrect')}</Badge>
              </div>
              <p className="text-sm text-muted-foreground">{t('exams.marksAwarded', { marks: answer.marks_awarded })}</p>
              {answer.explanation && <p className="mt-2 text-sm text-muted-foreground">{answer.explanation}</p>}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
