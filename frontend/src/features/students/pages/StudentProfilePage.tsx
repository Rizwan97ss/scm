import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowRightLeft, GraduationCap, IdCard, LogOut, RotateCcw, TrendingUp, UserPlus } from 'lucide-react'
import { idCardsApi } from '@/api/endpoints/certificates'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar, Badge, Dropdown, Skeleton, Tabs } from '@/components/ui'
import { STUDENT_STATUS_LABELS, GENDER_LABELS } from '@/types/enums'
import { formatDate } from '@/utils/formatDate'
import { routePaths } from '@/routes/routePaths'
import { StudentGuardianList } from '../components/StudentGuardianList'
import { StudentDocumentUploader } from '../components/StudentDocumentUploader'
import { StudentRemarksTab } from '../components/StudentRemarksTab'
import { StudentEnrollmentHistoryTimeline } from '../components/StudentEnrollmentHistoryTimeline'
import { StudentEnrollmentActionModal, type EnrollmentActionType } from '../components/StudentEnrollmentActionModal'
import { StudentAttendanceHistory } from '@/features/attendance/components/StudentAttendanceHistory'
import { StudentExamResultsTab } from '@/features/exams/components/StudentExamResultsTab'
import { StudentFeesTab } from '@/features/fees/components/StudentFeesTab'
import type { ApiError } from '@/api/client'

export function StudentProfilePage() {
  const { id } = useParams<{ id: string }>()
  const studentId = Number(id)
  const { can } = usePermission()
  const [action, setAction] = useState<EnrollmentActionType | null>(null)

  const { data: student, isLoading } = useQuery({ queryKey: queryKeys.student(studentId), queryFn: () => studentsApi.get(studentId) })

  const inviteMutation = useMutation({
    mutationFn: () => studentsApi.invitePortalUser(studentId),
    onSuccess: (result) => {
      toast.success(`Portal account ready. Username: ${result.username} · Temp password: ${result.temporary_password}`, { duration: 15000 })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (isLoading || !student) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  const canManageEnrollment = can('enrollment.manage')

  return (
    <div>
      <PageHeader
        title={student.full_name}
        breadcrumbs={[{ label: 'Students', to: routePaths.students }, { label: student.full_name }]}
        actions={
          <>
            <a
              href={idCardsApi.studentPdfUrl(studentId)}
              target="_blank"
              rel="noreferrer"
              className="flex items-center gap-1 rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
            >
              <IdCard className="h-4 w-4" /> ID Card
            </a>
            {can('students.edit') && (
              <button
                type="button"
                onClick={() => inviteMutation.mutate()}
                className="text-sm text-primary hover:underline"
                disabled={inviteMutation.isPending}
              >
                <UserPlus className="mr-1 inline h-4 w-4" />
                Invite to portal
              </button>
            )}
            {canManageEnrollment && student.status === 'active' && (
              <Dropdown
                trigger={
                  <button type="button" className="rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted">
                    Enrollment Actions
                  </button>
                }
                items={[
                  { label: 'Promote', icon: <TrendingUp className="h-4 w-4" />, onSelect: () => setAction('promote') },
                  { label: 'Transfer Out', icon: <ArrowRightLeft className="h-4 w-4" />, onSelect: () => setAction('transfer') },
                  { label: 'Graduate', icon: <GraduationCap className="h-4 w-4" />, onSelect: () => setAction('graduate') },
                  { label: 'Withdraw', icon: <LogOut className="h-4 w-4" />, destructive: true, onSelect: () => setAction('withdraw') },
                ]}
              />
            )}
            {canManageEnrollment && student.status !== 'active' && student.status !== 'graduated' && (
              <button
                type="button"
                onClick={() => setAction('reactivate')}
                className="flex items-center gap-1 rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
              >
                <RotateCcw className="h-4 w-4" /> Reactivate
              </button>
            )}
          </>
        }
      />

      <div className="mb-6 flex flex-col gap-4 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:gap-6">
        <Avatar name={student.full_name} src={student.photo_url} size={64} />
        <div className="grid flex-1 grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
          <div>
            <p className="text-muted-foreground">Admission #</p>
            <p className="font-medium">{student.admission_number}</p>
          </div>
          <div>
            <p className="text-muted-foreground">Status</p>
            <Badge variant={student.status === 'active' ? 'success' : 'default'}>{STUDENT_STATUS_LABELS[student.status]}</Badge>
          </div>
          <div>
            <p className="text-muted-foreground">Grade / Section</p>
            <p className="font-medium">
              {student.grade_level?.name ?? '—'} {student.section ? `- ${student.section.name}` : ''}
            </p>
          </div>
          <div>
            <p className="text-muted-foreground">Date of Birth</p>
            <p className="font-medium">
              {formatDate(student.date_of_birth)} ({GENDER_LABELS[student.gender]})
            </p>
          </div>
        </div>
      </div>

      <Tabs
        items={[
          {
            value: 'overview',
            label: 'Overview',
            content: (
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <InfoBlock label="Nationality" value={student.nationality} />
                <InfoBlock label="Blood group" value={student.blood_group} />
                <InfoBlock label="Roll number" value={student.roll_number} />
                <InfoBlock label="Admission date" value={formatDate(student.admission_date)} />
                <InfoBlock label="Previous school" value={student.previous_school_name} />
                <InfoBlock label="Emergency contact" value={student.emergency_contact_name ? `${student.emergency_contact_name} (${student.emergency_contact_phone})` : null} />
                <InfoBlock label="Address" value={[student.address_line1, student.city, student.state].filter(Boolean).join(', ') || null} />
                <InfoBlock label="Medical info" value={student.medical_info} />
              </div>
            ),
          },
          { value: 'attendance', label: 'Attendance', content: <StudentAttendanceHistory studentId={student.id} /> },
          { value: 'exams', label: 'Exams', content: <StudentExamResultsTab student={student} /> },
          { value: 'remarks', label: 'Remarks', content: <StudentRemarksTab studentId={student.id} /> },
          { value: 'fees', label: 'Fees', content: <StudentFeesTab studentId={student.id} /> },
          { value: 'guardians', label: 'Guardians', content: <StudentGuardianList student={student} /> },
          { value: 'documents', label: 'Documents', content: <StudentDocumentUploader studentId={student.id} /> },
          { value: 'history', label: 'Enrollment History', content: <StudentEnrollmentHistoryTimeline studentId={student.id} /> },
        ]}
      />

      <StudentEnrollmentActionModal student={student} action={action} onOpenChange={(open) => !open && setAction(null)} />
    </div>
  )
}

function InfoBlock({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="text-sm font-medium">{value || '—'}</p>
    </div>
  )
}
