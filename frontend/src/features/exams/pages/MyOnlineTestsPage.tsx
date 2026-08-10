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
  const navigate = useNavigate()
  const { data: tests, isLoading } = useQuery({ queryKey: queryKeys.myOnlineTests, queryFn: onlineTestsApi.mine })

  return (
    <div>
      <PageHeader title="My Online Tests" description="Auto-graded tests for your section — results appear the moment you submit." />

      {isLoading && <Skeleton className="h-48 w-full" />}

      {!isLoading && (tests?.length ?? 0) === 0 && (
        <EmptyState title="No online tests right now" description="Check back once your teacher schedules one." />
      )}

      {!isLoading && (tests?.length ?? 0) > 0 && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Exam</TableHead>
              <TableHead>Subject</TableHead>
              <TableHead>Duration</TableHead>
              <TableHead>Window</TableHead>
              <TableHead>Attempts</TableHead>
              <TableHead>Best Score</TableHead>
              <TableHead></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {tests!.map((test) => {
              const exhausted = test.attempts_used >= test.max_attempts && test.best_score !== null
              return (
                <TableRow key={test.exam_subject_id}>
                  <TableCell className="font-medium">{test.exam_name}</TableCell>
                  <TableCell>{test.subject_name}</TableCell>
                  <TableCell>{test.duration_minutes ? `${test.duration_minutes} min` : 'No limit'}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {test.online_starts_at ? formatDate(test.online_starts_at) : 'Open'} – {test.online_ends_at ? formatDate(test.online_ends_at) : 'No deadline'}
                  </TableCell>
                  <TableCell>{test.attempts_used} / {test.max_attempts}</TableCell>
                  <TableCell>{test.best_score !== null ? <Badge variant="success">{test.best_score} / {test.max_score}</Badge> : '—'}</TableCell>
                  <TableCell className="text-right">
                    <Button size="sm" disabled={exhausted} onClick={() => navigate(routePaths.takeOnlineTest(test.exam_subject_id))}>
                      <PlayCircle className="h-3.5 w-3.5" /> {test.attempts_used > 0 ? 'Retake' : 'Start'}
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
