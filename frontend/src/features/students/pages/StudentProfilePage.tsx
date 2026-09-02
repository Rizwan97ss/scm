import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ArrowRightLeft, GraduationCap, IdCard, LogOut, RotateCcw, TrendingUp, UserPlus } from 'lucide-react'
import { idCardsApi } from '@/api/endpoints/certificates'
import { studentsApi } from '@/api/endpoints/students'
import { queryKeys } from '@/api/queryKeys'
import { usePermission } from '@/hooks/usePermission'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar, Badge, Dropdown, QueryErrorState, Skeleton, Tabs } from '@/components/ui'
import { STUDENT_STATUS_LABEL_KEYS, GENDER_LABEL_KEYS } from '@/types/enums'
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
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const studentId = Number(id)
  const { can } = usePermission()
  const [action, setAction] = useState<EnrollmentActionType | null>(null)

  const { data: student, isLoading, isError, refetch } = useQuery({ queryKey: queryKeys.student(studentId), queryFn: () => studentsApi.get(studentId) })

  const inviteMutation = useMutation({
    mutationFn: () => studentsApi.invitePortalUser(studentId),
    onSuccess: (result) => {
      toast.success(t('students.portalAccountReadyToast', { username: result.username, password: result.temporary_password }), { duration: 15000 })
    },
    onError: (error) => toast.error((error as ApiError).message),
  })

  if (isLoading) {
    return (
      <div className="flex flex-col gap-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  if (isError || !student) {
    return <QueryErrorState onRetry={refetch} />
  }

  const canManageEnrollment = can('enrollment.manage')

  return (
    <div>
      <PageHeader
        title={student.full_name}
        breadcrumbs={[{ label: t('nav.students'), to: routePaths.students }, { label: student.full_name }]}
        actions={
          <>
            <a
              href={idCardsApi.studentPdfUrl(studentId)}
              target="_blank"
              rel="noopener"
              className="flex items-center gap-1 rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
            >
              <IdCard className="h-4 w-4" /> {t('students.idCardLink')}
            </a>
            {can('students.edit') && (
              <button
                type="button"
                onClick={() => inviteMutation.mutate()}
                className="text-sm text-primary hover:underline"
                disabled={inviteMutation.isPending}
              >
                <UserPlus className="me-1 inline h-4 w-4" />
                {t('students.invitePortalAction')}
              </button>
            )}
            {canManageEnrollment && student.status === 'active' && (
              <Dropdown
                trigger={
                  <button type="button" className="rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted">
                    {t('students.enrollmentActionsMenu')}
                  </button>
                }
                items={[
                  { label: t('students.promoteAction'), icon: <TrendingUp className="h-4 w-4" />, onSelect: () => setAction('promote') },
                  { label: t('students.transferOutAction'), icon: <ArrowRightLeft className="h-4 w-4" />, onSelect: () => setAction('transfer') },
                  { label: t('students.graduateAction'), icon: <GraduationCap className="h-4 w-4" />, onSelect: () => setAction('graduate') },
                  { label: t('students.withdrawAction'), icon: <LogOut className="h-4 w-4" />, destructive: true, onSelect: () => setAction('withdraw') },
                ]}
              />
            )}
            {canManageEnrollment && student.status !== 'active' && student.status !== 'graduated' && (
              <button
                type="button"
                onClick={() => setAction('reactivate')}
                className="flex items-center gap-1 rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-muted"
              >
                <RotateCcw className="h-4 w-4" /> {t('students.reactivateAction')}
              </button>
            )}
          </>
        }
      />

      <div className="mb-6 flex flex-col gap-4 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-center sm:gap-6">
        <Avatar name={student.full_name} src={student.photo_url} size={64} />
        <div className="grid flex-1 grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-5">
          <div>
            <p className="text-muted-foreground">{t('students.admissionNumber')}</p>
            <p className="font-medium">{student.admission_number}</p>
          </div>
          <div>
            <p className="text-muted-foreground">{t('common.status')}</p>
            <Badge variant={student.status === 'active' ? 'success' : 'default'}>{t(STUDENT_STATUS_LABEL_KEYS[student.status])}</Badge>
          </div>
          <div>
            <p className="text-muted-foreground">{t('students.gradeSectionLabel')}</p>
            <p className="font-medium">
              {student.grade_level?.name ?? '—'} {student.section ? `- ${student.section.name}` : ''}
            </p>
          </div>
          <div>
            <p className="text-muted-foreground">{t('entities.department')}</p>
            <p className="font-medium">{student.department?.name ?? '—'}</p>
          </div>
          <div>
            <p className="text-muted-foreground">{t('students.dateOfBirthLabel')}</p>
            <p className="font-medium">
              {formatDate(student.date_of_birth)} ({t(GENDER_LABEL_KEYS[student.gender])})
            </p>
          </div>
        </div>
      </div>

      <Tabs
        items={[
          {
            value: 'overview',
            label: t('students.overviewTab'),
            content: (
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <InfoBlock label={t('students.nationalityLabel')} value={student.nationality} />
                <InfoBlock label={t('students.bloodGroupLabel')} value={student.blood_group} />
                <InfoBlock label={t('students.rollNumberLabel')} value={student.roll_number} />
                <InfoBlock label={t('students.admissionDateLabel')} value={formatDate(student.admission_date)} />
                <InfoBlock label={t('students.previousSchoolLabel')} value={student.previous_school_name} />
                <InfoBlock
                  label={t('students.emergencyContactInfoLabel')}
                  value={student.emergency_contact_name ? `${student.emergency_contact_name} (${student.emergency_contact_phone})` : null}
                />
                <InfoBlock label={t('common.address')} value={[student.address_line1, student.city, student.state].filter(Boolean).join(', ') || null} />
                <InfoBlock label={t('students.medicalInfoLabel')} value={student.medical_info} />
              </div>
            ),
          },
          { value: 'attendance', label: t('nav.attendance'), content: <StudentAttendanceHistory studentId={student.id} /> },
          { value: 'exams', label: t('nav.exams'), content: <StudentExamResultsTab student={student} /> },
          { value: 'remarks', label: t('students.remarksTab'), content: <StudentRemarksTab studentId={student.id} /> },
          { value: 'fees', label: t('students.feesTab'), content: <StudentFeesTab studentId={student.id} /> },
          { value: 'guardians', label: t('students.guardiansHeading'), content: <StudentGuardianList student={student} /> },
          { value: 'documents', label: t('students.documentsTab'), content: <StudentDocumentUploader studentId={student.id} /> },
          { value: 'history', label: t('students.historyTab'), content: <StudentEnrollmentHistoryTimeline studentId={student.id} /> },
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
