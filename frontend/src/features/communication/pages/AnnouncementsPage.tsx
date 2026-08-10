import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Megaphone, Send } from 'lucide-react'
import { announcementsApi } from '@/api/endpoints/communication'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, Checkbox, DataTable, FormField, Input, Modal, Select, Textarea, type DataTableColumn } from '@/components/ui'
import { formatDateTime } from '@/utils/formatDate'
import { ANNOUNCEMENT_CHANNEL_LABELS, ANNOUNCEMENT_CHANNELS } from '@/types/communication'
import { AUDIENCES, AUDIENCE_LABELS } from '@/types/noticeBoard'
import type { Announcement, AnnouncementChannel, AnnouncementPayload } from '@/types/communication'
import type { ApiError } from '@/api/client'

export function AnnouncementsPage() {
  const { can } = usePermission()
  const canManage = can('communication.manage')
  const { setPage, queryParams } = usePagination('-sent_at')
  const listQuery = useQuery({ queryKey: queryKeys.announcements(queryParams), queryFn: () => announcementsApi.list(queryParams) })

  const [composeOpen, setComposeOpen] = useState(false)

  const columns: DataTableColumn<Announcement>[] = [
    { key: 'title', header: 'Title', render: (row) => <span className="font-medium">{row.title}</span> },
    { key: 'audience', header: 'Audience', render: (row) => <Badge>{row.audience_label}</Badge> },
    { key: 'channels', header: 'Channels', render: (row) => row.channels.map((c) => ANNOUNCEMENT_CHANNEL_LABELS[c]).join(', ') },
    { key: 'recipient_count', header: 'Recipients', align: 'right', render: (row) => row.recipient_count },
    { key: 'sent_by', header: 'Sent By', render: (row) => row.sent_by?.full_name ?? '—' },
    { key: 'sent_at', header: 'Sent', render: (row) => formatDateTime(row.sent_at) },
  ]

  return (
    <div>
      <PageHeader
        title="Announcements"
        description="Broadcast a message to students, staff, or parents."
        actions={
          canManage && (
            <Button onClick={() => setComposeOpen(true)}>
              <Megaphone className="h-4 w-4" /> Compose Announcement
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
        emptyTitle="No announcements sent yet"
      />

      {composeOpen && <ComposeModal open={composeOpen} onOpenChange={setComposeOpen} />}
    </div>
  )
}

const EMPTY_FORM: AnnouncementPayload = { title: '', body: '', audience: 'all', channels: ['in_app'] }

function ComposeModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<AnnouncementPayload>(EMPTY_FORM)

  function toggleChannel(channel: AnnouncementChannel, checked: boolean) {
    setForm((prev) => ({
      ...prev,
      channels: checked ? [...prev.channels, channel] : prev.channels.filter((c) => c !== channel),
    }))
  }

  const mutation = useMutation({
    mutationFn: () => announcementsApi.send(form),
    onSuccess: (announcement) => {
      toast.success(`Announcement sent to ${announcement.recipient_count} recipient(s).`)
      queryClient.invalidateQueries({ queryKey: queryKeys.announcements().slice(0, 1) })
      setForm(EMPTY_FORM)
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Compose Announcement">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Title" htmlFor="title" required>
          <Input id="title" required value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} />
        </FormField>
        <FormField label="Message" htmlFor="body" required>
          <Textarea id="body" rows={4} required value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} />
        </FormField>
        <FormField label="Audience" htmlFor="audience" required>
          <Select
            id="audience"
            value={form.audience}
            onValueChange={(value) => setForm({ ...form, audience: value as AnnouncementPayload['audience'] })}
            options={AUDIENCES.map((a) => ({ value: a, label: AUDIENCE_LABELS[a] }))}
          />
        </FormField>
        <FormField label="Channels" htmlFor="channels" required>
          <div className="flex flex-wrap gap-4">
            {ANNOUNCEMENT_CHANNELS.map((channel) => (
              <label key={channel} className="flex items-center gap-2 text-sm">
                <Checkbox checked={form.channels.includes(channel)} onCheckedChange={(checked) => toggleChannel(channel, checked)} />
                {ANNOUNCEMENT_CHANNEL_LABELS[channel]}
              </label>
            ))}
          </div>
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} disabled={form.channels.length === 0} className="mt-2">
          <Send className="h-4 w-4" /> Send announcement
        </Button>
      </form>
    </Modal>
  )
}
