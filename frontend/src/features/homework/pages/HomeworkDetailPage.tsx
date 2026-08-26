import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { FileText, Save } from 'lucide-react'
import { homeworkApi, homeworkSubmissionsApi } from '@/api/endpoints/homework'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Input, Skeleton, Table, TableBody, TableCell, TableHead, TableHeader, TableRow, Textarea } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import { HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS } from '@/types/homework'
import type { ApiError } from '@/api/client'

interface GradeState {
  score: string
  feedback: string
}

export function HomeworkDetailPage() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const homeworkId = Number(id)
  const { can } = usePermission()
  const queryClient = useQueryClient()

  const { data: homework, isLoading: homeworkLoading } = useQuery({ queryKey: queryKeys.homeworkItem(homeworkId), queryFn: () => homeworkApi.get(homeworkId) })
  const { data: roster, isLoading: rosterLoading } = useQuery({ queryKey: queryKeys.homeworkRoster(homeworkId), queryFn: () => homeworkApi.roster(homeworkId) })

  const [drafts, setDrafts] = useState<Record<number, GradeState>>({})

  const gradeMutation = useMutation({
    mutationFn: ({ submissionId, score, feedback }: { submissionId: number; score: string; feedback: string }) =>
      homeworkSubmissionsApi.grade(submissionId, { score: score === '' ? null : Number(score), feedback: feedback || null }),
    onSuccess: () => {
      toast.success(t('homework.gradeSavedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.homeworkRoster(homeworkId) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (homeworkLoading || !homework) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  const canGrade = can('homework.grade')

  return (
    <div>
      <PageHeader
        title={homework.title}
        description={`${homework.subject?.name ?? ''} · ${homework.section?.name ?? ''} · ${t('homework.dueLabel')} ${formatDate(homework.due_date)}${homework.max_score ? ` · ${t('homework.maxScoreInline', { score: homework.max_score })}` : ''}`}
        breadcrumbs={[{ label: t('nav.homework'), to: routePaths.homework }, { label: homework.title }]}
      />

      {homework.description && <p className="mb-4 text-sm text-muted-foreground">{homework.description}</p>}

      {homework.attachments.length > 0 && (
        <div className="mb-6 flex flex-col gap-1.5">
          {homework.attachments.map((attachment) => (
            <a key={attachment.id} href={attachment.url} target="_blank" rel="noopener" className="flex items-center gap-2 text-sm text-primary hover:underline">
              <FileText className="h-4 w-4 shrink-0" /> {attachment.file_name}
            </a>
          ))}
        </div>
      )}

      {rosterLoading && (
        <div className="flex flex-col gap-2">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-12 w-full" />
          ))}
        </div>
      )}

      {!rosterLoading && (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('students.admissionNumber')}</TableHead>
              <TableHead>{t('common.name')}</TableHead>
              <TableHead>{t('common.status')}</TableHead>
              <TableHead>{t('homework.submissionColumn')}</TableHead>
              <TableHead>{t('homework.scoreColumn')}</TableHead>
              <TableHead>{t('homework.feedbackColumn')}</TableHead>
              {canGrade && <TableHead />}
            </TableRow>
          </TableHeader>
          <TableBody>
            {(roster ?? []).map(({ student, submission }) => {
              const draft = drafts[student.id] ?? { score: submission?.score !== null && submission?.score !== undefined ? String(submission.score) : '', feedback: submission?.feedback ?? '' }

              return (
                <TableRow key={student.id}>
                  <TableCell className="text-muted-foreground">{student.admission_number}</TableCell>
                  <TableCell className="font-medium">{student.full_name}</TableCell>
                  <TableCell>
                    {submission ? (
                      <Badge variant={submission.status === 'graded' ? 'success' : 'info'}>{t(HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS[submission.status])}</Badge>
                    ) : (
                      <Badge variant="outline">{t('homework.notSubmittedBadge')}</Badge>
                    )}
                  </TableCell>
                  <TableCell className="max-w-xs">
                    {submission?.content && <p className="line-clamp-2 text-sm text-muted-foreground">{submission.content}</p>}
                    {submission?.attachments.map((attachment) => (
                      <a key={attachment.id} href={attachment.url} target="_blank" rel="noopener" className="block text-sm text-primary hover:underline">
                        {attachment.file_name}
                      </a>
                    ))}
                  </TableCell>
                  <TableCell>
                    {canGrade && submission ? (
                      <Input
                        type="number"
                        min="0"
                        max={homework.max_score ?? undefined}
                        className="h-8 w-20"
                        value={draft.score}
                        onChange={(e) => setDrafts((prev) => ({ ...prev, [student.id]: { ...draft, score: e.target.value } }))}
                      />
                    ) : (
                      submission?.score ?? '—'
                    )}
                  </TableCell>
                  <TableCell>
                    {canGrade && submission ? (
                      <Textarea
                        className="min-h-16 w-48"
                        rows={2}
                        value={draft.feedback}
                        onChange={(e) => setDrafts((prev) => ({ ...prev, [student.id]: { ...draft, feedback: e.target.value } }))}
                      />
                    ) : (
                      submission?.feedback ?? '—'
                    )}
                  </TableCell>
                  {canGrade && (
                    <TableCell>
                      {submission && (
                        <Button
                          size="sm"
                          variant="outline"
                          aria-label={t('homework.saveGradeAria', { name: student.full_name })}
                          isLoading={gradeMutation.isPending && gradeMutation.variables?.submissionId === submission.id}
                          onClick={() => gradeMutation.mutate({ submissionId: submission.id, score: draft.score, feedback: draft.feedback })}
                        >
                          <Save className="h-3.5 w-3.5" />
                        </Button>
                      )}
                    </TableCell>
                  )}
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
