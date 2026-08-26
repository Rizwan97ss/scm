import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { parentPortalApi } from '@/api/endpoints/dashboard'
import { queryKeys } from '@/api/queryKeys'
import { Badge, EmptyState, Skeleton } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'
import { REMARK_CATEGORY_LABEL_KEYS } from '@/types/homework'

/** Only remarks explicitly marked visible_to_guardian ever reach this endpoint — see StudentRemark::scopeVisibleTo(). */
export function ChildRemarksTab({ studentId }: { studentId: number }) {
  const { t } = useTranslation()
  const { data: remarks, isLoading } = useQuery({ queryKey: queryKeys.parentChildRemarks(studentId), queryFn: () => parentPortalApi.childRemarks(studentId) })

  if (isLoading) return <Skeleton className="h-48 w-full" />
  if (!remarks?.length) return <EmptyState title={t('students.noRemarksYet')} />

  return (
    <ul className="flex flex-col gap-3">
      {remarks.map((remark) => (
        <li key={remark.id} className="rounded-lg border border-border p-4">
          <div className="mb-1 flex items-center gap-2">
            <Badge variant="outline">{t(REMARK_CATEGORY_LABEL_KEYS[remark.category])}</Badge>
            <span className="ms-auto text-xs text-muted-foreground">{formatDateTime(remark.created_at)}</span>
          </div>
          <p className="text-sm">{remark.body}</p>
          {remark.author && <p className="mt-1 text-xs text-muted-foreground">— {remark.author.full_name}</p>}
        </li>
      ))}
    </ul>
  )
}
