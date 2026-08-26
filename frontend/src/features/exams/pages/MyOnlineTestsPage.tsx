import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { PlayCircle } from 'lucide-react'
import { onlineTestsApi } from '@/api/endpoints/exams'
import { queryKeys } from '@/api/queryKeys'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, EmptyState, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui'
import { routePaths } from '@/routes/routePaths'
import { formatDate } from '@/utils/formatDate'

export function MyOnlineTestsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { data: tests, isLoading } = useQuery({ queryKey: queryKeys.myOnlineTests, queryFn: onlineTestsApi.mine })

  return (
    <div>
      <PageHeader title={t('nav.my_online_tests')} description={t('exams.myOnlineTestsDescription')} />

      {isLoading && <Skeleton className="h-48 w-full" />}

      {!isLoading && (tests?.length ?? 0) === 0 && (
        <EmptyState title={t('exams.noOnlineTestsTitle')} description={t('exams.noOnlineTestsDescription')} />
      )}

      {!isLoading && (tests?.length ?? 0) > 0 && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('entities.exam')}</TableHead>
              <TableHead>{t('entities.subject')}</TableHead>
              <TableHead>{t('exams.duration')}</TableHead>
              <TableHead>{t('exams.window')}</TableHead>
              <TableHead>{t('exams.attempts')}</TableHead>
              <TableHead>{t('exams.bestScore')}</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {tests!.map((test) => {
              // A submitted attempt always counts toward attempts_used, but an
              // in-progress one is always resumable regardless of the cap (see
              // OnlineExamService::startAttempt()) — so this only needs the raw
              // count, not best_score, which is now masked until declared and
              // would otherwise make an exhausted, undeclared test look retakeable.
              const exhausted = test.attempts_used >= test.max_attempts
              return (
                <TableRow key={test.exam_subject_id}>
                  <TableCell className="font-medium">{test.exam_name}</TableCell>
                  <TableCell>{test.subject_name}</TableCell>
                  <TableCell>{test.duration_minutes ? t('exams.minutesAbbrev', { count: test.duration_minutes }) : t('exams.noLimit')}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {test.online_starts_at ? formatDate(test.online_starts_at) : t('exams.open')} – {test.online_ends_at ? formatDate(test.online_ends_at) : t('exams.noDeadline')}
                  </TableCell>
                  <TableCell>{test.attempts_used} / {test.max_attempts}</TableCell>
                  <TableCell>
                    {test.result_declared && test.best_score !== null && <Badge variant="success">{test.best_score} / {test.max_score}</Badge>}
                    {!test.result_declared && test.attempts_used > 0 && <Badge variant="warning">{t('exams.pending')}</Badge>}
                    {!test.result_declared && test.attempts_used === 0 && '—'}
                  </TableCell>
                  <TableCell className="text-end">
                    <Button size="sm" disabled={exhausted} onClick={() => navigate(routePaths.takeOnlineTest(test.exam_subject_id))}>
                      <PlayCircle className="h-3.5 w-3.5" /> {test.attempts_used > 0 ? t('exams.retake') : t('exams.start')}
                    </Button>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
