import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Search, Send } from 'lucide-react'
import { guardiansApi } from '@/api/endpoints/guardians'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, Input, type DataTableColumn } from '@/components/ui'
import type { Guardian } from '@/types/student'
import type { ApiError } from '@/api/client'

export function GuardiansListPage() {
  const { can } = usePermission()
  const queryClient = useQueryClient()
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('first_name', 'first_name')
  const debouncedSearch = useDebounce(search)

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.guardians({ ...queryParams, 'filter[first_name]': debouncedSearch }),
    queryFn: () => guardiansApi.list({ ...queryParams, 'filter[first_name]': debouncedSearch || undefined }),
  })

  const inviteMutation = useMutation({
    mutationFn: guardiansApi.invite,
    onSuccess: () => {
      toast.success('Invite sent.')
      queryClient.invalidateQueries({ queryKey: queryKeys.guardians() })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<Guardian>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.full_name}</span> },
    { key: 'phone', header: 'Phone', render: (row) => row.phone },
    { key: 'email', header: 'Email', render: (row) => row.email ?? '—' },
    { key: 'students', header: 'Children', render: (row) => row.students?.map((s) => s.full_name).join(', ') || '—' },
    {
      key: 'portal',
      header: 'Portal Access',
      render: (row) => (row.has_portal_access ? <Badge variant="success">Active</Badge> : <Badge variant="default">Not invited</Badge>),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        can('guardians.edit') &&
        !row.has_portal_access &&
        row.email && (
          <Button variant="outline" size="sm" onClick={() => inviteMutation.mutate(row.id)} isLoading={inviteMutation.isPending}>
            <Send className="h-3.5 w-3.5" /> Invite
          </Button>
        ),
    },
  ]

  return (
    <div>
      <PageHeader title="Guardians" description="Parents and guardians linked to students at your school." />

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
        emptyTitle="No guardians found"
      />
    </div>
  )
}
