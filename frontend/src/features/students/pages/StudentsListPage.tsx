import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Download, Plus, TrendingUp, Upload } from 'lucide-react'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, BulkActionBar, Button, DataTable, LinkButton, SearchInput, type DataTableColumn } from '@/components/ui'
import { STUDENT_STATUS_LABEL_KEYS } from '@/types/enums'
import type { Student } from '@/types/student'
import { routePaths } from '@/routes/routePaths'
import { downloadFile } from '@/utils/download'
import { useNavigate } from 'react-router-dom'
import { BulkPromoteModal } from '../components/BulkPromoteModal'

export function StudentsListPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const navigate = useNavigate()
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('first_name', 'first_name')
  const debouncedSearch = useDebounce(search)
  const [selectedIds, setSelectedIds] = useState<Set<string | number>>(new Set())
  const [promoteModalOpen, setPromoteModalOpen] = useState(false)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: queryKeys.students({ ...queryParams, 'filter[first_name]': debouncedSearch }),
    queryFn: () => studentsApi.list({ ...queryParams, 'filter[first_name]': debouncedSearch || undefined }),
  })

  const columns: DataTableColumn<Student>[] = [
    { key: 'admission_number', header: t('students.admissionNumber'), render: (row) => row.admission_number },
    { key: 'name', header: t('common.name'), sortable: true, render: (row) => <span className="font-medium">{row.full_name}</span> },
    { key: 'grade_level', header: t('students.grade'), render: (row) => row.grade_level?.name ?? '—' },
    { key: 'section', header: t('students.section'), render: (row) => row.section?.name ?? '—' },
    { key: 'department', header: t('students.department'), render: (row) => row.department?.name ?? '—' },
    {
      key: 'status',
      header: t('common.status'),
      render: (row) => (
        <Badge variant={row.status === 'active' ? 'success' : row.status === 'withdrawn' || row.status === 'transferred_out' ? 'destructive' : 'default'}>
          {t(STUDENT_STATUS_LABEL_KEYS[row.status])}
        </Badge>
      ),
    },
  ]

  const selectedIdList = Array.from(selectedIds).map(Number)

  return (
    <div>
      <PageHeader
        title={t('students.title')}
        description={t('students.description')}
        actions={
          <>
            {can('students.export') && (
              <Button variant="outline" onClick={() => downloadFile(studentsApi.exportUrl, 'students.xlsx')}>
                <Download className="h-4 w-4" /> {t('common.export')}
              </Button>
            )}
            {can('students.import') && (
              <LinkButton to={routePaths.studentImport} variant="outline">
                <Upload className="h-4 w-4" /> {t('common.import')}
              </LinkButton>
            )}
            {can('students.create') && (
              <LinkButton to={routePaths.studentAdmission}>
                <Plus className="h-4 w-4" /> {t('dashboard.newAdmission')}
              </LinkButton>
            )}
          </>
        }
      />

      <div className="mb-4 max-w-sm">
        <SearchInput placeholder={t('students.searchByName')} value={search} onChange={setSearch} />
      </div>

      <BulkActionBar count={selectedIds.size} onClear={() => setSelectedIds(new Set())}>
        {can('students.export') && (
          <Button
            variant="outline"
            size="sm"
            onClick={() => downloadFile(`${studentsApi.exportUrl}?ids=${selectedIdList.join(',')}`, 'students-selected.xlsx')}
          >
            <Download className="h-3.5 w-3.5" /> {t('common.exportSelected')}
          </Button>
        )}
        {can('enrollment.manage') && (
          <Button variant="outline" size="sm" onClick={() => setPromoteModalOpen(true)}>
            <TrendingUp className="h-3.5 w-3.5" /> {t('students.promoteSelected')}
          </Button>
        )}
      </BulkActionBar>

      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        isError={isError}
        onRetry={refetch}
        meta={data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        onRowClick={(row) => navigate(routePaths.studentProfile(row.id))}
        emptyTitle={t('students.noStudentsFound')}
        emptyDescription={can('students.create') ? t('students.admitFirstStudent') : undefined}
        selectedKeys={selectedIds}
        onSelectionChange={setSelectedIds}
      />

      <BulkPromoteModal
        studentIds={selectedIdList}
        open={promoteModalOpen}
        onOpenChange={(open) => {
          setPromoteModalOpen(open)
          if (!open) setSelectedIds(new Set())
        }}
      />
    </div>
  )
}
