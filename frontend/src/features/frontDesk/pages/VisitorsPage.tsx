import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { LogOut, Plus } from 'lucide-react'
import { visitorsApi } from '@/api/endpoints/frontDesk'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Textarea, type DataTableColumn } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'
import type { Visitor, VisitorPayload } from '@/types/frontDesk'
import type { ApiError } from '@/api/client'

const EMPTY_FORM: VisitorPayload = { name: '', phone: '', purpose: '', whom_to_meet: '', notes: '' }

export function VisitorsPage() {
  const { t } = useTranslation()
  const { can } = usePermission()
  const canManage = can('front-desk.manage')
  const { setPage, queryParams } = usePagination('-check_in_time')
  const listQuery = useQuery({ queryKey: queryKeys.visitors(queryParams), queryFn: () => visitorsApi.list(queryParams) })
  const queryClient = useQueryClient()

  const [checkInModalOpen, setCheckInModalOpen] = useState(false)

  const checkOutMutation = useMutation({
    mutationFn: (id: number) => visitorsApi.checkOut(id),
    onSuccess: () => {
      toast.success(t('frontDesk.visitorCheckedOutToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.visitors().slice(0, 1) })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const columns: DataTableColumn<Visitor>[] = [
    { key: 'name', header: t('common.name'), render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'purpose', header: t('frontDesk.purposeLabel'), render: (row) => row.purpose },
    { key: 'whom_to_meet', header: t('frontDesk.toMeetLabel'), render: (row) => row.whom_to_meet ?? '—' },
    { key: 'check_in', header: t('frontDesk.checkedInColumn'), render: (row) => formatDateTime(row.check_in_time) },
    { key: 'check_out', header: t('frontDesk.checkedOutColumn'), render: (row) => (row.check_out_time ? formatDateTime(row.check_out_time) : '—') },
    {
      key: 'status',
      header: t('common.status'),
      render: (row) => (
        <Badge variant={row.check_out_time ? 'default' : 'success'}>{row.check_out_time ? t('frontDesk.checkedOutBadge') : t('frontDesk.checkedInBadge')}</Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) =>
        canManage && !row.check_out_time ? (
          <Button
            variant="outline"
            size="sm"
            isLoading={checkOutMutation.isPending && checkOutMutation.variables === row.id}
            onClick={() => checkOutMutation.mutate(row.id)}
          >
            <LogOut className="h-3.5 w-3.5" /> {t('frontDesk.checkOutAction')}
          </Button>
        ) : null,
    },
  ]

  return (
    <div>
      <PageHeader
        title={t('nav.visitors')}
        description={t('frontDesk.description')}
        actions={
          canManage && (
            <Button onClick={() => setCheckInModalOpen(true)}>
              <Plus className="h-4 w-4" /> {t('frontDesk.logVisitorAction')}
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
        emptyTitle={t('frontDesk.noVisitorsYet')}
      />

      {checkInModalOpen && <CheckInModal open={checkInModalOpen} onOpenChange={setCheckInModalOpen} />}
    </div>
  )
}

function CheckInModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [form, setForm] = useState<VisitorPayload>(EMPTY_FORM)

  const mutation = useMutation({
    mutationFn: () => visitorsApi.checkIn(form),
    onSuccess: () => {
      toast.success(t('frontDesk.visitorCheckedInToast'))
      queryClient.invalidateQueries({ queryKey: queryKeys.visitors().slice(0, 1) })
      setForm(EMPTY_FORM)
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={t('frontDesk.logVisitorAction')}>
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label={t('common.name')} htmlFor="name" required>
          <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
        </FormField>
        <div className="grid grid-cols-2 gap-4">
          <FormField label={t('common.phone')} htmlFor="phone">
            <Input id="phone" value={form.phone ?? ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          </FormField>
          <FormField label={t('frontDesk.toMeetLabel')} htmlFor="whom_to_meet">
            <Input id="whom_to_meet" value={form.whom_to_meet ?? ''} onChange={(e) => setForm({ ...form, whom_to_meet: e.target.value })} />
          </FormField>
        </div>
        <FormField label={t('frontDesk.purposeLabel')} htmlFor="purpose" required>
          <Input id="purpose" required value={form.purpose} onChange={(e) => setForm({ ...form, purpose: e.target.value })} />
        </FormField>
        <FormField label={t('common.notes')} htmlFor="notes" hint={t('common.optional')}>
          <Textarea id="notes" rows={2} value={form.notes ?? ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} className="mt-2">
          {t('frontDesk.checkInSubmit')}
        </Button>
      </form>
    </Modal>
  )
}
