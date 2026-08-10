import { useQuery } from '@tanstack/react-query'
import { Download, Plus, Search, Upload } from 'lucide-react'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, Input, LinkButton, type DataTableColumn } from '@/components/ui'
import { STUDENT_STATUS_LABELS } from '@/types/enums'
import type { Student } from '@/types/student'
import { routePaths } from '@/routes/routePaths'
import { downloadFile } from '@/utils/download'
import { useNavigate } from 'react-router-dom'

export function StudentsListPage() {
  const { can } = usePermission()
  const navigate = useNavigate()
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('first_name', 'first_name')
  const debouncedSearch = useDebounce(search)

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.students({ ...queryParams, 'filter[first_name]': debouncedSearch }),
    queryFn: () => studentsApi.list({ ...queryParams, 'filter[first_name]': debouncedSearch || undefined }),
  })

  const columns: DataTableColumn<Student>[] = [
    { key: 'admission_number', header: 'Admission #', render: (row) => row.admission_number },
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.full_name}</span> },
    { key: 'grade_level', header: 'Grade', render: (row) => row.grade_level?.name ?? '—' },
    { key: 'section', header: 'Section', render: (row) => row.section?.name ?? '—' },
    {
      key: 'status',
      header: 'Status',
      render: (row) => (
        <Badge variant={row.status === 'active' ? 'success' : row.status === 'withdrawn' || row.status === 'transferred_out' ? 'destructive' : 'default'}>
          {STUDENT_STATUS_LABELS[row.status]}
        </Badge>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Students"
        description="Every student enrolled at your school."
        actions={
          <>
            {can('students.export') && (
              <Button variant="outline" onClick={() => downloadFile(studentsApi.exportUrl, 'students.xlsx')}>
                <Download className="h-4 w-4" /> Export
              </Button>
            )}
            {can('students.import') && (
              <LinkButton to={routePaths.studentImport} variant="outline">
                <Upload className="h-4 w-4" /> Import
              </LinkButton>
            )}
            {can('students.create') && (
              <LinkButton to={routePaths.studentAdmission}>
                <Plus className="h-4 w-4" /> New Admission
              </LinkButton>
            )}
          </>
        }
      />

      <div className="mb-4 max-w-sm">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input placeholder="Search by name…" className="pl-9" value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        meta={data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        onRowClick={(row) => navigate(routePaths.studentProfile(row.id))}
        emptyTitle="No students found"
        emptyDescription={can('students.create') ? 'Admit your first student to get started.' : undefined}
      />
    </div>
  )
}
