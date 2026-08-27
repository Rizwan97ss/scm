import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { AlertTriangle, CheckCircle2, Clock, Lock, Send, ShieldAlert } from 'lucide-react'
import { onlineTestsApi } from '@/api/endpoints/exams'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Card, CardContent, EmptyState } from '@/components/ui'
import type { OnlineTestAttempt, OnlineTestStatus, TestQuestion, ViolationEventType } from '@/types/exam'
import type { ApiError } from '@/api/client'

function formatTime(totalSeconds: number): string {
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60
  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
  }
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

/** How long a tab/app switch or fullscreen exit is tolerated before it counts as a violation — long enough to absorb the
 *  browser's own momentary focus flicker (e.g. its fullscreen-confirm dialog), nowhere near long enough to actually cheat in. */
const VIOLATION_DEBOUNCE_MS = 1000

function requestFullscreenBestEffort(): void {
  document.documentElement.requestFullscreen?.().catch(() => {
    // Denied, unsupported, or not a user-gesture context — the exam still
    // proceeds; there's simply nothing to "exit" later in that case.
  })
}

function exitFullscreenBestEffort(): void {
  if (document.fullscreenElement) {
    document.exitFullscreen?.().catch(() => {})
  }
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
  const [serverNowOffset, setServerNowOffset] = useState<number | null>(null)
  const [countdownToOpen, setCountdownToOpen] = useState<number | null>(null)

  // Guards against a real violation firing twice (e.g. a tab switch trips
  // both visibilitychange and window blur for the same event) and against
  // reporting one after the attempt is already on its way to being submitted.
  const violationReportedRef = useRef(false)
  const wasFullscreenRef = useRef(false)

  const { data: status } = useQuery<OnlineTestStatus>({
    queryKey: ['online-test-status', id],
    queryFn: () => onlineTestsApi.onlineStatus(id),
    enabled: phase === 'landing',
  })

  useEffect(() => {
    if (!status) return
    setServerNowOffset(new Date(status.server_time).getTime() - Date.now())
  }, [status])

  const earlyOpensAtMs = status?.online_starts_at && serverNowOffset !== null
    ? new Date(status.online_starts_at).getTime() - status.early_access_minutes * 60_000
    : null

  useEffect(() => {
    if (earlyOpensAtMs === null || serverNowOffset === null) {
      setCountdownToOpen(null)
      return
    }

    const tick = () => {
      const effectiveNow = Date.now() + serverNowOffset
      const secondsLeft = Math.max(0, Math.round((earlyOpensAtMs - effectiveNow) / 1000))
      setCountdownToOpen(secondsLeft)
    }

    tick()
    const interval = setInterval(tick, 1000)
    return () => clearInterval(interval)
  }, [earlyOpensAtMs, serverNowOffset])

  const startMutation = useMutation({
    mutationFn: () => onlineTestsApi.start(id),
    onSuccess: (data) => {
      setAttempt(data.attempt)
      setQuestions(data.questions)
      if (data.duration_minutes) {
        const startedAtMs = new Date(data.attempt.started_at).getTime()
        setDeadline(startedAtMs + data.duration_minutes * 60_000)
      }
      violationReportedRef.current = false
      wasFullscreenRef.current = !!document.fullscreenElement
      setPhase('in-progress')
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const answerMutation = useMutation({
    mutationFn: ({ questionId, optionId }: { questionId: number; optionId: number }) => onlineTestsApi.saveAnswer(attempt!.id, questionId, optionId),
  })

  function finishAttempt(data: OnlineTestAttempt) {
    exitFullscreenBestEffort()
    setResult(data)
    setPhase('result')
  }

  const submitMutation = useMutation({
    mutationFn: () => onlineTestsApi.submit(attempt!.id),
    onSuccess: (data) => {
      finishAttempt(data)
      toast.success(t('exams.testSubmittedToast'))
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const violationMutation = useMutation({
    mutationFn: (eventType: ViolationEventType) => onlineTestsApi.reportViolation(attempt!.id, eventType),
    onSuccess: (data) => finishAttempt(data),
    onError: (error) => toast.error((error as ApiError).message),
  })

  function reportViolationOnce(eventType: ViolationEventType) {
    if (violationReportedRef.current) return
    violationReportedRef.current = true
    violationMutation.mutate(eventType)
  }

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

  // Keeps the account's login lock alive (see backend LoginRequest) for as
  // long as this tab is genuinely still open — a dropped connection simply
  // stops refreshing it, so another device can log back in and resume
  // after a short gap rather than being locked out indefinitely.
  useEffect(() => {
    if (phase !== 'in-progress' || !attempt) return

    const interval = setInterval(() => {
      onlineTestsApi.heartbeat(attempt.id).catch(() => {})
    }, 20_000)

    return () => clearInterval(interval)
  }, [phase, attempt])

  // Zero-tolerance integrity monitoring: a tab switch, an app switch (window
  // losing OS focus), or exiting fullscreen all end the attempt immediately.
  // Each listener starts a short debounce on the "away" edge and cancels it
  // on the "back" edge, so a momentary flicker from the browser's own UI
  // (e.g. its fullscreen-confirm dialog) can't cause a false submit.
  useEffect(() => {
    if (phase !== 'in-progress') return

    let hiddenTimer: ReturnType<typeof setTimeout> | null = null
    let blurTimer: ReturnType<typeof setTimeout> | null = null
    let fullscreenTimer: ReturnType<typeof setTimeout> | null = null

    function handleVisibilityChange() {
      if (document.hidden) {
        hiddenTimer = setTimeout(() => reportViolationOnce('tab_hidden'), VIOLATION_DEBOUNCE_MS)
      } else if (hiddenTimer) {
        clearTimeout(hiddenTimer)
        hiddenTimer = null
      }
    }

    function handleBlur() {
      blurTimer = setTimeout(() => reportViolationOnce('window_blur'), VIOLATION_DEBOUNCE_MS)
    }

    function handleFocus() {
      if (blurTimer) {
        clearTimeout(blurTimer)
        blurTimer = null
      }
    }

    function handleFullscreenChange() {
      const isFullscreen = !!document.fullscreenElement
      if (isFullscreen) {
        wasFullscreenRef.current = true
        if (fullscreenTimer) {
          clearTimeout(fullscreenTimer)
          fullscreenTimer = null
        }
        return
      }
      if (wasFullscreenRef.current) {
        fullscreenTimer = setTimeout(() => reportViolationOnce('fullscreen_exit'), VIOLATION_DEBOUNCE_MS)
      }
    }

    function handleBeforeUnload(event: BeforeUnloadEvent) {
      event.preventDefault()
      event.returnValue = ''
    }

    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('blur', handleBlur)
    window.addEventListener('focus', handleFocus)
    document.addEventListener('fullscreenchange', handleFullscreenChange)
    window.addEventListener('beforeunload', handleBeforeUnload)

    return () => {
      if (hiddenTimer) clearTimeout(hiddenTimer)
      if (blurTimer) clearTimeout(blurTimer)
      if (fullscreenTimer) clearTimeout(fullscreenTimer)
      document.removeEventListener('visibilitychange', handleVisibilityChange)
      window.removeEventListener('blur', handleBlur)
      window.removeEventListener('focus', handleFocus)
      document.removeEventListener('fullscreenchange', handleFullscreenChange)
      window.removeEventListener('beforeunload', handleBeforeUnload)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase])

  function selectOption(questionId: number, optionId: number) {
    setAnswers((prev) => ({ ...prev, [questionId]: optionId }))
    answerMutation.mutate({ questionId, optionId })
  }

  function handleStartClick() {
    // Must run synchronously inside the click handler, not inside the
    // mutation's async callback — some browsers only honor a fullscreen
    // request made directly within a user-gesture event.
    requestFullscreenBestEffort()
    startMutation.mutate()
  }

  if (phase === 'landing') {
    const notYetOpen = countdownToOpen !== null && countdownToOpen > 0

    return (
      <div className="mx-auto max-w-lg">
        <PageHeader title={t('exams.onlineTest')} />
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
            {notYetOpen ? (
              <>
                <Lock className="h-8 w-8 text-muted-foreground" />
                <p className="font-medium">{t('exams.notYetOpenTitle')}</p>
                {status?.online_starts_at && (
                  <p className="text-sm text-muted-foreground">
                    {t('exams.notYetOpenMessage', { time: new Date(status.online_starts_at).toLocaleString() })}
                  </p>
                )}
                <Badge variant="outline" className="flex items-center gap-1">
                  <Clock className="h-3.5 w-3.5" /> {t('exams.opensInLabel')} {formatTime(countdownToOpen)}
                </Badge>
              </>
            ) : (
              <>
                <AlertTriangle className="h-8 w-8 text-warning" />
                <p className="text-sm text-muted-foreground">{t('exams.startTestWarning')}</p>
                <Button onClick={handleStartClick} isLoading={startMutation.isPending}>
                  {t('exams.startTest')}
                </Button>
              </>
            )}
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

        <div className="flex select-none flex-col gap-4" onCopy={(e) => e.preventDefault()} onContextMenu={(e) => e.preventDefault()}>
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

  if (result.auto_submit_reason === 'violation' || result.auto_submit_reason === 'time_expired') {
    return (
      <div className="mx-auto max-w-lg">
        <PageHeader title={t('exams.autoSubmittedViolationTitle')} />
        <Card>
          <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
            <ShieldAlert className="h-8 w-8 text-destructive" />
            <p className="text-sm text-muted-foreground">
              {result.auto_submit_reason === 'violation'
                ? t('exams.autoSubmittedViolationMessage')
                : t('exams.autoSubmittedTimeExpiredMessage')}
            </p>
          </CardContent>
        </Card>
      </div>
    )
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
