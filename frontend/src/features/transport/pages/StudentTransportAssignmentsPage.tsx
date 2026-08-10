import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'
import { routesApi, studentTransportAssignmentsApi, vehiclesApi } from '@/api/endpoints/transport'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePagination } from '@/hooks/usePagination'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, Button, DataTable, FormField, Input, Modal, Select, type DataTableColumn } from '@/components/ui'
import { formatDate } from '@/utils/formatDate'
import type { StudentTransportAssignment, StudentTransportAssignmentPayload } from '@/types/transport'
import type { ApiError } from '@/api/client'

export function StudentTransportAssignmentsPage() {
  const { can } = usePermission()
  const canManage = can('transport.manage')
  const { setPage, queryParams } = usePagination('-effective_from')
  const listQuery = useQuery({ queryKey: queryKeys.studentTransportAssignments(queryParams), queryFn: () => studentTransportAssignmentsApi.list(queryParams) })

  const [assignModalOpen, setAssignModalOpen] = useState(false)

  const columns: DataTableColumn<StudentTransportAssignment>[] = [
    { key: 'student', header: 'Student', render: (row) => row.student?.full_name ?? '—' },
    { key: 'route', header: 'Route', render: (row) => row.route?.name ?? '—' },
    { key: 'route_stop', header: 'Stop', render: (row) => row.route_stop?.name ?? '—' },
    { key: 'vehicle', header: 'Vehicle', render: (row) => row.vehicle?.registration_number ?? '—' },
    { key: 'effective_from', header: 'Effective From', render: (row) => formatDate(row.effective_from) },
    { key: 'status', header: 'Status', render: (row) => <Badge variant={row.is_active ? 'success' : 'default'}>{row.is_active ? 'Active' : 'Superseded'}</Badge> },
  ]

  return (
    <div>
      <PageHeader
        title="Student Transport Assignments"
        description="Which route, stop, and vehicle each student is assigned to."
        actions={
          canManage && (
            <Button onClick={() => setAssignModalOpen(true)}>
              <Plus className="h-4 w-4" /> Assign Student
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
        emptyTitle="No transport assignments yet"
      />

      {assignModalOpen && <AssignModal open={assignModalOpen} onOpenChange={setAssignModalOpen} />}
    </div>
  )
}

function AssignModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: students } = useQuery({ queryKey: queryKeys.students({ per_page: 100 }), queryFn: () => studentsApi.list({ per_page: 100 }) })
  const { data: routes } = useQuery({ queryKey: queryKeys.routes({ per_page: 100 }), queryFn: () => routesApi.list({ per_page: 100 }) })
  const { data: vehicles } = useQuery({ queryKey: queryKeys.vehicles({ per_page: 100 }), queryFn: () => vehiclesApi.list({ per_page: 100 }) })

  const [form, setForm] = useState<StudentTransportAssignmentPayload>({
    student_id: 0,
    route_id: 0,
    route_stop_id: 0,
    vehicle_id: null,
    effective_from: new Date().toISOString().slice(0, 10),
  })

  const selectedRoute = routes?.data.find((r) => r.id === form.route_id)

  const mutation = useMutation({
    mutationFn: () => studentTransportAssignmentsApi.assign(form),
    onSuccess: () => {
      toast.success('Student assigned to transport.')
      queryClient.invalidateQueries({ queryKey: queryKeys.studentTransportAssignments().slice(0, 1) })
      onOpenChange(false)
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  return (
    <Modal open={open} onOpenChange={onOpenChange} title="Assign Student to Transport">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          mutation.mutate()
        }}
        className="flex flex-col gap-4"
        noValidate
      >
        <FormField label="Student" htmlFor="student_id" required>
          <Select
            id="student_id"
            value={form.student_id ? String(form.student_id) : undefined}
            onValueChange={(value) => setForm({ ...form, student_id: Number(value) })}
            options={(students?.data ?? []).map((s) => ({ value: String(s.id), label: `${s.full_name} (${s.admission_number})` }))}
            placeholder="Select a student"
          />
        </FormField>
        <FormField label="Route" htmlFor="route_id" required>
          <Select
            id="route_id"
            value={form.route_id ? String(form.route_id) : undefined}
            onValueChange={(value) => setForm({ ...form, route_id: Number(value), route_stop_id: 0 })}
            options={(routes?.data ?? []).map((r) => ({ value: String(r.id), label: r.name }))}
            placeholder="Select a route"
          />
        </FormField>
        <FormField label="Stop" htmlFor="route_stop_id" required>
          <Select
            id="route_stop_id"
            value={form.route_stop_id ? String(form.route_stop_id) : undefined}
            onValueChange={(value) => setForm({ ...form, route_stop_id: Number(value) })}
            options={(selectedRoute?.stops ?? []).map((s) => ({ value: String(s.id), label: s.name }))}
            placeholder={selectedRoute ? 'Select a stop' : 'Select a route first'}
            disabled={!selectedRoute}
          />
        </FormField>
        <FormField label="Vehicle" htmlFor="vehicle_id" hint="Optional">
          <Select
            id="vehicle_id"
            value={form.vehicle_id ? String(form.vehicle_id) : undefined}
            onValueChange={(value) => setForm({ ...form, vehicle_id: Number(value) })}
            options={(vehicles?.data ?? []).map((v) => ({ value: String(v.id), label: v.registration_number }))}
            placeholder="Select a vehicle"
          />
        </FormField>
        <FormField label="Effective From" htmlFor="effective_from" required>
          <Input id="effective_from" type="date" required value={form.effective_from} onChange={(e) => setForm({ ...form, effective_from: e.target.value })} />
        </FormField>
        <Button type="submit" isLoading={mutation.isPending} disabled={!form.student_id || !form.route_id || !form.route_stop_id} className="mt-2">
          Assign
        </Button>
      </form>
    </Modal>
  )
}
