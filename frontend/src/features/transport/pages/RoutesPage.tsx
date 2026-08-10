import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ListPlus, Plus, Trash2 } from 'lucide-react'
import { routesApi } from '@/api/endpoints/transport'
import { queryKeys } from '@/api/queryKeys'
import { useCrudResource } from '@/hooks/useCrudResource'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, ConfirmDialog, DataTable, FormField, Input, Modal, type DataTableColumn } from '@/components/ui'
import type { RoutePayload, RouteStopPayload, TransportRoute } from '@/types/transport'
import type { ApiError } from '@/api/client'

const EMPTY_STOP: RouteStopPayload = { name: '' }
const EMPTY_FORM: RoutePayload = { name: '', description: '', is_active: true, stops: [{ ...EMPTY_STOP }] }

export function RoutesPage() {
  const { can } = usePermission()
  const canManage = can('transport.manage')
  const { sort, setPage, setSort, queryParams } = usePagination('name', 'name')
  const { listQuery, createMutation, removeMutation } = useCrudResource(routesApi, queryKeys.routes, queryParams, 'Route')

  const [modalOpen, setModalOpen] = useState(false)
  const [form, setForm] = useState<RoutePayload>(EMPTY_FORM)
  const [deleting, setDeleting] = useState<TransportRoute | null>(null)
  const [managingStops, setManagingStops] = useState<TransportRoute | null>(null)

  function openCreate() {
    setForm(EMPTY_FORM)
    setModalOpen(true)
  }

  async function onSubmit(event: React.FormEvent) {
    event.preventDefault()
    await createMutation.mutateAsync(form)
    setModalOpen(false)
  }

  const columns: DataTableColumn<TransportRoute>[] = [
    { key: 'name', header: 'Name', sortable: true, render: (row) => <span className="font-medium">{row.name}</span> },
    { key: 'description', header: 'Description', render: (row) => row.description ?? '—' },
    { key: 'stops', header: 'Stops', align: 'right', render: (row) => row.stops?.length ?? 0 },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (row) => (
        <div className="flex justify-end gap-2">
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => setManagingStops(row)}>
              <ListPlus className="h-3.5 w-3.5" /> Stops
            </Button>
          )}
          {canManage && (
            <Button variant="outline" size="sm" onClick={() => setDeleting(row)} aria-label={`Delete ${row.name}`}>
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
        title="Routes"
        description="Transport routes and their stops."
        actions={
          canManage && (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" /> New Route
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
        emptyTitle="No routes yet"
      />

      <Modal open={modalOpen} onOpenChange={setModalOpen} title="New Route" size="lg">
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          <FormField label="Name" htmlFor="name" required>
            <Input id="name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} />
          </FormField>
          <FormField label="Description" htmlFor="description">
            <Input id="description" value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
          </FormField>

          <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold">Stops</h3>
              <Button type="button" variant="outline" size="sm" onClick={() => setForm((prev) => ({ ...prev, stops: [...(prev.stops ?? []), { ...EMPTY_STOP }] }))}>
                <Plus className="h-3.5 w-3.5" /> Add stop
              </Button>
            </div>
            {(form.stops ?? []).map((stop, index) => (
              <div key={index} className="flex items-end gap-2">
                <div className="flex-1">
                  <label className="text-xs text-muted-foreground">Stop name</label>
                  <Input
                    required
                    value={stop.name}
                    onChange={(e) => setForm((prev) => ({ ...prev, stops: (prev.stops ?? []).map((s, i) => (i === index ? { ...s, name: e.target.value } : s)) }))}
                  />
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  onClick={() => setForm((prev) => ({ ...prev, stops: (prev.stops ?? []).filter((_, i) => i !== index) }))}
                  disabled={(form.stops ?? []).length <= 1}
                  aria-label="Remove stop"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            ))}
          </div>

          <Button type="submit" isLoading={createMutation.isPending} className="mt-2">
            Create route
          </Button>
        </form>
      </Modal>

      {managingStops && <ManageStopsModal route={managingStops} open={!!managingStops} onOpenChange={(open) => !open && setManagingStops(null)} />}

      <ConfirmDialog
        open={!!deleting}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="This cannot be undone."
        isLoading={removeMutation.isPending}
        onConfirm={async () => {
          if (deleting) await removeMutation.mutateAsync(deleting.id)
          setDeleting(null)
        }}
      />
    </div>
  )
}

function ManageStopsModal({ route, open, onOpenChange }: { route: TransportRoute; open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [newStopName, setNewStopName] = useState('')

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: queryKeys.routes().slice(0, 1) })
  }

  const addMutation = useMutation({
    mutationFn: () => routesApi.addStop(route.id, { name: newStopName }),
    onSuccess: () => {
      toast.success('Stop added.')
      setNewStopName('')
      invalidate()
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  const removeMutation = useMutation({
    mutationFn: (stopId: number) => routesApi.removeStop(route.id, stopId),
    onSuccess: () => {
      toast.success('Stop removed.')
      invalidate()
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title={`Stops — ${route.name}`}>
      <div className="flex flex-col gap-4">
        <ul className="flex flex-col gap-2">
          {route.stops.map((stop) => (
            <li key={stop.id} className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
              <span>
                {stop.sequence}. {stop.name}
              </span>
              <Button variant="outline" size="icon" isLoading={removeMutation.isPending} onClick={() => removeMutation.mutate(stop.id)} aria-label={`Remove ${stop.name}`}>
                <Trash2 className="h-3.5 w-3.5" />
              </Button>
            </li>
          ))}
          {route.stops.length === 0 && <p className="text-sm text-muted-foreground">No stops yet.</p>}
        </ul>
        <form
          onSubmit={(e) => {
            e.preventDefault()
            addMutation.mutate()
          }}
          className="flex items-end gap-2"
          noValidate
        >
          <FormField label="New stop" htmlFor="new_stop_name" required className="flex-1">
            <Input id="new_stop_name" required value={newStopName} onChange={(e) => setNewStopName(e.target.value)} />
          </FormField>
          <Button type="submit" isLoading={addMutation.isPending}>
            Add
          </Button>
        </form>
      </div>
    </Modal>
  )
}
