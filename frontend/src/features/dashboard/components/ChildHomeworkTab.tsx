import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { FileText } from 'lucide-react'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { Badge, EmptyState, Skeleton } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import { HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS } from '@/types/homework'

/**
 * Read-only — the Parent Portal never lets a parent submit on their child's
 * behalf, so this just surfaces what's assigned and its status, via the
 * dedicated /parent/children/{id}/homework endpoint (never the staff-facing
 * /homework, same rationale as ChildExamResultsTab).
 */
export function ChildHomeworkTab({ studentId }: { studentId: number }) {
  const { t } = useTranslation()
  const { data: homework, isLoading } = useQuery({ queryKey: queryKeys.parentChildHomework(studentId), queryFn: () => parentPortalApi.childHomework(studentId) })

  if (isLoading) return <Skeleton className="h-48 w-full" />
  if (!homework?.length) return <EmptyState title={t('common.noItemsYet', { items: t('nav.homework') })} />

  return (
    <ul className="flex flex-col gap-3">
      {homework.map((item) => {
        const status = item.my_submission?.status
        return (
          <li key={item.id} className="rounded-lg border border-border p-4">
            <div className="mb-1 flex items-center justify-between gap-2">
              <span className="font-medium">{item.title}</span>
              {status ? (
                <Badge variant={status === 'graded' ? 'success' : 'info'}>{t(HOMEWORK_SUBMISSION_STATUS_LABEL_KEYS[status])}</Badge>
              ) : (
                <Badge variant="outline">{t('homework.notSubmittedBadge')}</Badge>
              )}
            </div>
            <p className="text-xs text-muted-foreground">
              {item.subject?.name} · {t('homework.dueLabel')} {formatDate(item.due_date)}
              {item.max_score ? ` · ${t('homework.maxScoreInline', { score: item.max_score })}` : ''}
            </p>
            {item.description && <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>}
            {item.attachments.map((attachment) => (
              <a key={attachment.id} href={attachment.url} target="_blank" rel="noopener" className="mt-1 flex items-center gap-2 text-sm text-primary hover:underline">
                <FileText className="h-4 w-4 shrink-0" /> {attachment.file_name}
              </a>
            ))}
            {item.my_submission?.status === 'graded' && (
              <div className="mt-2 rounded-md bg-muted/50 p-2 text-sm">
                <span className="font-medium">
                  {t('homework.scoreColumn')}: {item.my_submission.score}
                  {item.max_score ? ` / ${item.max_score}` : ''}
                </span>
                {item.my_submission.feedback && <p className="text-muted-foreground">{item.my_submission.feedback}</p>}
              </div>
            )}
          </li>
        )
      })}
    </ul>
  )
}
