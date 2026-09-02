import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { RotateCcw } from 'lucide-react'
import { bookIssuesApi } from '@/api/endpoints/library'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, type DataTableColumn } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import { formatCurrency } from '@/utils/formatCurrency'
import { BOOK_ISSUE_STATUS_LABEL_KEYS } from '@/types/library'
import type { BookIssue } from '@/types/library'
import type { ApiError } from '@/api/client'

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'destructive' | 'default'> = {
  issued: 'warning',
  returned: 'success',
  overdue: 'destructive',
}

export function BookIssuesPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const canManage = can('library.manage')
  const { setPage, queryParams } = usePagination('-created_at')
  const listQuery = useQuery({ queryKey: queryKeys.bookIssues(queryParams), queryFn: () => bookIssuesApi.list(queryParams) })
  const queryClient = useQueryClient()

  const returnMutation = useMutation({
    mutationFn: (id: number) => bookIssuesApi.returnBook(id),
    onSuccess: () => {
      toast.success(t('library.bookReturnedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.bookIssues().slice(0, 1) })
      queryClient.invalidateQueries({ queryKey: queryKeys.books().slice(0, 1) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<BookIssue>[] = [
    { key: 'book', header: t('entities.book'), render: (row) => row.book?.title ?? '—' },
    { key: 'borrower', header: t('library.borrower'), render: (row) => row.borrower_name },
    { key: 'issue_date', header: t('fees.issued'), render: (row) => formatDate(row.issue_date) },
    { key: 'due_date', header: t('fees.dueDate'), render: (row) => formatDate(row.due_date) },
    { key: 'return_date', header: t('library.returned'), render: (row) => (row.return_date ? formatDate(row.return_date) : '—') },
    { key: 'fine', header: t('library.fine'), align: 'right', render: (row) => (row.fine_amount > 0 ? formatCurrency(row.fine_amount) : '—') },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={STATUS_VARIANT[row.status] ?? 'default'}>{t(BOOK_ISSUE_STATUS_LABEL_KEYS[row.status])}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        canManage && row.status !== 'returned' ? (
          <Button
            variant="outline"
            size="sm"
            isLoading={returnMutation.isPending && returnMutation.variables === row.id}
            onClick={() => returnMutation.mutate(row.id)}
          >
            <RotateCcw className="h-3.5 w-3.5" /> {t('library.returnAction')}
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader title={t('nav.book_issues')} description={t('library.bookIssuesDescription')} />

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading} isError={listQuery.isError} onRetry={listQuery.refetch}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        emptyTitle={t('common.noItemsYet', { items: t('nav.book_issues') })}
        emptyDescription={t('library.bookIssuesEmptyDescription')}
      />
    </div>
  )
}
