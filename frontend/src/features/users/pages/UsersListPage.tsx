import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { IdCard, Plus, Search } from 'lucide-react'
import { usersApi } from '@/api/endpoints/users'
import { idCardsApi } from '@/api/endpoints/certificates'
import { rolesApi } from '@/api/endpoints/roles'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { useDebounce } from '@/hooks/useDebounce'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, Input, type DataTableColumn } from '@/components/ui'
import { USER_STATUS_LABELS } from '@/types/enums'
import type { User } from '@/types/auth'
import { UserFormModal } from '../components/UserFormModal'
import { UserStatusMenu } from '../components/UserStatusMenu'

export function UsersListPage() {
  const { can } = usePermission()
  const { sort, search, setPage, setSort, setSearch, queryParams } = usePagination('first_name', 'first_name')
  const debouncedSearch = useDebounce(search)

  const { data, isLoading } = useQuery({
    queryKey: queryKeys.users({ ...queryParams, 'filter[first_name]': debouncedSearch }),
    queryFn: () => usersApi.list({ ...queryParams, 'filter[first_name]': debouncedSearch || undefined }),
  })
  const { data: roles } = useQuery({ queryKey: queryKeys.roles(), queryFn: rolesApi.list })

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<User | null>(null)

  const columns: DataTableColumn<User>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.full_name}</span> },
    { key: 'email', header: 'Email', render: (row) => row.email },
    { key: 'roles', header: 'Roles', render: (row) => <div className="flex flex-wrap gap-1">{row.roles.map((role) => <Badge key={role} variant="primary">{role}</Badge>)}</div> },
    {
      key: 'status',
      header: 'Status',
      render: (row) => (
        <Badge variant={row.status === 'active' ? 'success' : row.status === 'suspended' ? 'destructive' : 'default'}>
          {USER_STATUS_LABELS[row.status]}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          <a
            href={idCardsApi.staffPdfUrl(row.id)}
            target="_blank"
            rel="noopener"
            className="flex items-center gap-1 rounded-md border border-border px-3 py-1.5 text-sm font-medium hover:bg-muted"
            aria-label={`Download ${row.full_name}'s ID card`}
          >
            <IdCard className="h-3.5 w-3.5" />
          </a>
          {can('users.edit') && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                setEditing(row)
                setModalOpen(true)
              }}
            >
              Edit
            </Button>
          )}
          {can('users.edit') && <UserStatusMenu user={row} />}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title="Staff & Users"
        description="Manage every user account and their roles."
        actions={
          can('users.create') && (
            <Button
              onClick={() => {
                setEditing(null)
                setModalOpen(true)
              }}
            >
              <Plus className="h-4 w-4" /> New User
            </Button>
          )
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
        emptyTitle="No users found"
      />

      <UserFormModal open={modalOpen} onOpenChange={setModalOpen} editing={editing} roles={roles ?? []} />
    </div>
  )
}
