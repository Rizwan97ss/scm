import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Plus, Send, Trash2 } from 'lucide-react'
import { booksApi } from '@/api/endpoints/library'
import { studentsApi } from '@/api/endpoints/students'
import { usersApi } from '@/api/endpoints/users'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, ConfirmDialog, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import type { Book, BookPayload } from '@/types/library'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: BookPayload = { title: '', author: '', isbn: '', category: '', total_copies: 1, is_active: true }

export function BooksPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const canManage = can('library.manage')
  const { sort, setPage, setSort, queryParams } = usePagination('title', 'title')
  const { listQuery, createMutation, updateMutation, removeMutation } = useCrudResource(booksApi, queryKeys.books, queryParams, 'Book')

  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState<Book | null>(null)
  const [form, setForm] = useState<BookPayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<Book | null>(null)
  const [issuing, setIssuing] = useState<Book | null>(null)

  function openCreate() {
    setEditing(null)
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  function openEdit(book: Book) {
    setEditing(book)
    setForm({ title: book.title, author: book.author ?? '', isbn: book.isbn ?? '', category: book.category ?? '', total_copies: book.total_copies, is_active: book.is_active })
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    if (editing) await updateMutation.mutateAsync({ id: editing.id, payload: form })
    else await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<Book>[] = [
    { key: 'title', header: t('common.name'), sortable: true, render: (row) => <span className="font-medium">{row.title}</span> },
    { key: 'author', header: t('library.author'), render: (row) => row.author ?? '—' },
    { key: 'category', header: t('entities.category'), render: (row) => row.category ?? '—' },
    { key: 'copies', header: t('library.availableTotal'), align: 'right', render: (row) => `${row.available_copies} / ${row.total_copies}` },
    { key: 'status', header: t('common.status'), render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? t('common.active') : t('common.inactive')}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {canManage && row.available_copies > 0 && (
            <Button variant="outline" size="sm" onClick={() => setIssuing(row)}>
              <Send className="h-3.5 w-3.5" /> {t('library.issueAction')}
            </Button>
          )}
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => openEdit(row)}>
              {t('common.edit')}
            </Button>
          )}
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={t('common.deleteItem', { item: row.title })}>
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.books')}
        description={t('library.booksDescription')}
        actions={
          canManage && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> {t('common.newItem', { item: t('entities.book') })}
            </Button>
          )
        }
      />

      <DataTable
        columns={columns}
        data={listQuery.data?.data}
        rowKey={(row) => row.id}
        isLoading={listQuery.isLoading}
        meta={listQuery.data?.meta}
        onPageChange={setPage}
        sort={sort}
        onSortChange={setSort}
        emptyTitle={t('common.noItemsYet', { items: t('nav.books') })}
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title={editing ? t('common.editItem', { item: t('entities.book') }) : t('common.newItem', { item: t('entities.book') })}>
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label={t('common.name')} htmlFor="title" required>
            <Input id="title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
          </FormField>
          <div className="grid grid-cols-2 gap-4">
            <FormField label={t('library.author')} htmlFor="author">
              <Input id="author" value={form.author ?? ''} onChange={(e) => setForm({ ...form, author: e.target.value })} />
            </FormField>
            <FormField label={t('entities.category')} htmlFor="category">
              <Input id="category" value={form.category ?? ''} onChange={(e) => setForm({ ...form, category: e.target.value })} />
            </FormField>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <FormField label={t('library.isbn')} htmlFor="isbn">
              <Input id="isbn" value={form.isbn ?? ''} onChange={(e) => setForm({ ...form, isbn: e.target.value })} />
            </FormField>
            <FormField label={t('library.totalCopies')} htmlFor="total_copies" required>
              <Input
                id="total_copies"
                type="number"
                min={1}
                required
                value={form.total_copies}
                onChange={(e) => setForm({ ...form, total_copies: Number(e.target.value) })}
              />
            </FormField>
          </div>
          <FormField label={t('common.active')} htmlFor="is_active">
            <Checkbox id="is_active" checked={form.is_active} onCheckedChange={(checked) => setForm({ ...form, is_active: checked })} />
          </FormField>
          <Button type="submit" isLoading={createMutation.isPending || updateMutation.isPending} className="mt-2">
            {editing ? t('common.saveChanges') : t('common.createItem', { item: t('entities.book') })}
          </Button>
        </form>
      </Modal>

      {issuing && <IssueBookModal book={issuing} open={!!issuing} onOpenChange={(open) => !open && setIssuing(null)} />}

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={t('common.deleteConfirmTitle', { name: deleting?.title })}
        description={t('common.cannotBeUndone')}
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}

function IssueBookModal({ book, open, onOpenChange }: { book: Book; open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [borrowerType, setBorrowerType] = useState<'student' | 'user'>('student')
  const [borrowerId, setBorrowerId] = useState<number | undefined>()
  const [dueDate, setDueDate] = useState(() => {
    const d = new Date()
    d.setDate(d.getDate() + 14)
    return d.toISOString().slice(0, 10)
  })

  const { data: students } = useQuery({ queryKey: queryKeys.students({ per_page: 100 }), queryFn: () => studentsApi.list({ per_page: 100 }), enabled: borrowerType === 'student' })
  const { data: users } = useQuery({ queryKey: queryKeys.users({ per_page: 100 }), queryFn: () => usersApi.list({ per_page: 100 }), enabled: borrowerType === 'user' })

  const mutation = useMutation({
    mutationFn: () =>
      booksApi.issue(book.id, {
        student_id: borrowerType === 'student' ? borrowerId : null,
        user_id: borrowerType === 'user' ? borrowerId : null,
        due_date: dueDate,
      }),
    onSuccess: () => {
      toast.success(t('library.bookIssuedToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.books().slice(0, 1) })
      queryClient.invalidateQueries({ queryKey: queryKeys.bookIssues().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const borrowerTypeLabel = borrowerType === 'student' ? t('entities.student') : t('library.staffMember')

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('library.issueBookTitle', { title: book.title })}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label={t('library.borrowerType')} htmlFor="borrower_type" required>
          <Select
            id="borrower_type"
            value={borrowerType}
            onValueChange={(value) => {
              setBorrowerType(value as 'student' | 'user')
              setBorrowerId(undefined)
            }}
            options={[
              { value: 'student', label: t('entities.student') },
              { value: 'user', label: t('library.staffMember') },
            ]}
          />
        </FormField>
        <FormField label={borrowerTypeLabel} htmlFor="borrower_id" required>
          <Select
            id="borrower_id"
            value={borrowerId ? String(borrowerId) : undefined}
            onValueChange={(value) => setBorrowerId(Number(value))}
            placeholder={t('library.selectBorrower', { type: borrowerTypeLabel })}
            options={
              borrowerType === 'student'
                ? (students?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.full_name} (${s.admission_number})` }))
                : (users?.data ?? []).map((u) => ({ value: String(u.id), label: u.full_name }))
            }
          />
        </FormField>
        <FormField label={t('fees.dueDate')} htmlFor="due_date" required>
          <Input id="due_date" type="date" required value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} disabled={!borrowerId} className="mt-2">
          {t('library.issueBook')}
        </Button>
      </form>
    </Modal>
  )
}
