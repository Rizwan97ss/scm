import { lazy, Suspense } from 'react'
import { Routes, Route } from 'react-router-dom'
import { ProtectedRoute } from './ProtectedRoute'
import { PlatformProtectedRoute } from './PlatformProtectedRoute'
import { PermissionRoute } from './PermissionRoute'
import { RequireMfaSetup } from './RequireMfaSetup'
import { RequirePlatformMfaSetup } from './RequirePlatformMfaSetup'
import { routePaths } from './routePaths'
import { AppShell } from '@/components/layout/AppShell'
import { PlatformShell } from '@/components/layout/PlatformShell'
import { NotFound } from '@/components/feedback/NotFound'
import { LoadingScreen } from '@/components/feedback/LoadingScreen'
import { isCentralDomain } from '@/utils/tenant'

// Route-level code splitting: each feature page ships as its own chunk instead
// of one large bundle, so the initial load only pays for the login screen.
const LandingPage = lazy(() => import('@/features/marketing/pages/LandingPage').then((m) => ({ default: m.LandingPage })))
const LoginPage = lazy(() => import('@/features/auth/pages/LoginPage').then((m) => ({ default: m.LoginPage })))
const PlatformLoginPage = lazy(() => import('@/features/platform/pages/PlatformLoginPage').then((m) => ({ default: m.PlatformLoginPage })))
const SignupPage = lazy(() => import('@/features/signup/pages/SignupPage').then((m) => ({ default: m.SignupPage })))
const SignupCompletePage = lazy(() => import('@/features/signup/pages/SignupCompletePage').then((m) => ({ default: m.SignupCompletePage })))
const ForgotPasswordPage = lazy(() => import('@/features/auth/pages/ForgotPasswordPage').then((m) => ({ default: m.ForgotPasswordPage })))
const ResetPasswordPage = lazy(() => import('@/features/auth/pages/ResetPasswordPage').then((m) => ({ default: m.ResetPasswordPage })))
const DashboardPage = lazy(() => import('@/features/dashboard/pages/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const HelpGuidePage = lazy(() => import('@/features/help/pages/HelpGuidePage').then((m) => ({ default: m.HelpGuidePage })))
const MfaSetupPage = lazy(() => import('@/features/auth/pages/MfaSetupPage').then((m) => ({ default: m.MfaSetupPage })))
const PlatformMfaSetupPage = lazy(() => import('@/features/platform/pages/PlatformMfaSetupPage').then((m) => ({ default: m.PlatformMfaSetupPage })))
const UsersListPage = lazy(() => import('@/features/users/pages/UsersListPage').then((m) => ({ default: m.UsersListPage })))
const RolesListPage = lazy(() => import('@/features/roles/pages/RolesListPage').then((m) => ({ default: m.RolesListPage })))
const SettingsPage = lazy(() => import('@/features/settings/pages/SettingsPage').then((m) => ({ default: m.SettingsPage })))
const BillingPage = lazy(() => import('@/features/settings/pages/BillingPage').then((m) => ({ default: m.BillingPage })))
const AuditLogsPage = lazy(() => import('@/features/auditLogs/pages/AuditLogsPage').then((m) => ({ default: m.AuditLogsPage })))
const MyDataExportPage = lazy(() => import('@/features/dataExports/pages/MyDataExportPage').then((m) => ({ default: m.MyDataExportPage })))
const DataExportsPage = lazy(() => import('@/features/settings/pages/DataExportsPage').then((m) => ({ default: m.DataExportsPage })))
const SchoolsListPage = lazy(() => import('@/features/schools/pages/SchoolsListPage').then((m) => ({ default: m.SchoolsListPage })))
const AcademicYearsPage = lazy(() => import('@/features/academics/pages/AcademicYearsPage').then((m) => ({ default: m.AcademicYearsPage })))
const TermsPage = lazy(() => import('@/features/academics/pages/TermsPage').then((m) => ({ default: m.TermsPage })))
const DepartmentsPage = lazy(() => import('@/features/academics/pages/DepartmentsPage').then((m) => ({ default: m.DepartmentsPage })))
const GradeLevelsPage = lazy(() => import('@/features/academics/pages/GradeLevelsPage').then((m) => ({ default: m.GradeLevelsPage })))
const SectionsPage = lazy(() => import('@/features/academics/pages/SectionsPage').then((m) => ({ default: m.SectionsPage })))
const SubjectsPage = lazy(() => import('@/features/academics/pages/SubjectsPage').then((m) => ({ default: m.SubjectsPage })))
const RoomsPage = lazy(() => import('@/features/academics/pages/RoomsPage').then((m) => ({ default: m.RoomsPage })))
const HolidaysPage = lazy(() => import('@/features/academics/pages/HolidaysPage').then((m) => ({ default: m.HolidaysPage })))
const TimetablePage = lazy(() => import('@/features/academics/pages/TimetablePage').then((m) => ({ default: m.TimetablePage })))
const StudentsListPage = lazy(() => import('@/features/students/pages/StudentsListPage').then((m) => ({ default: m.StudentsListPage })))
const StudentAdmissionPage = lazy(() => import('@/features/students/pages/StudentAdmissionPage').then((m) => ({ default: m.StudentAdmissionPage })))
const StudentProfilePage = lazy(() => import('@/features/students/pages/StudentProfilePage').then((m) => ({ default: m.StudentProfilePage })))
const StudentImportPage = lazy(() => import('@/features/students/pages/StudentImportPage').then((m) => ({ default: m.StudentImportPage })))
const GuardiansListPage = lazy(() => import('@/features/students/pages/GuardiansListPage').then((m) => ({ default: m.GuardiansListPage })))
const TakeAttendancePage = lazy(() => import('@/features/attendance/pages/TakeAttendancePage').then((m) => ({ default: m.TakeAttendancePage })))
const StaffAttendancePage = lazy(() => import('@/features/attendance/pages/StaffAttendancePage').then((m) => ({ default: m.StaffAttendancePage })))
const ParentChildrenPage = lazy(() => import('@/features/dashboard/pages/ParentChildrenPage').then((m) => ({ default: m.ParentChildrenPage })))
const ParentChildProfilePage = lazy(() =>
  import('@/features/dashboard/pages/ParentChildProfilePage').then((m) => ({ default: m.ParentChildProfilePage }))
)
const GradingScalesPage = lazy(() => import('@/features/exams/pages/GradingScalesPage').then((m) => ({ default: m.GradingScalesPage })))
const ExamConfigurationPage = lazy(() => import('@/features/exams/pages/ExamConfigurationPage').then((m) => ({ default: m.ExamConfigurationPage })))
const QuestionBankPage = lazy(() => import('@/features/exams/pages/QuestionBankPage').then((m) => ({ default: m.QuestionBankPage })))
const ExamsListPage = lazy(() => import('@/features/exams/pages/ExamsListPage').then((m) => ({ default: m.ExamsListPage })))
const ExamDetailPage = lazy(() => import('@/features/exams/pages/ExamDetailPage').then((m) => ({ default: m.ExamDetailPage })))
const MarksEntryPage = lazy(() => import('@/features/exams/pages/MarksEntryPage').then((m) => ({ default: m.MarksEntryPage })))
const OnlineTestConfigPage = lazy(() => import('@/features/exams/pages/OnlineTestConfigPage').then((m) => ({ default: m.OnlineTestConfigPage })))
const TakeOnlineTestPage = lazy(() => import('@/features/exams/pages/TakeOnlineTestPage').then((m) => ({ default: m.TakeOnlineTestPage })))
const MyOnlineTestsPage = lazy(() => import('@/features/exams/pages/MyOnlineTestsPage').then((m) => ({ default: m.MyOnlineTestsPage })))
const HomeworkListPage = lazy(() => import('@/features/homework/pages/HomeworkListPage').then((m) => ({ default: m.HomeworkListPage })))
const HomeworkDetailPage = lazy(() => import('@/features/homework/pages/HomeworkDetailPage').then((m) => ({ default: m.HomeworkDetailPage })))
const FeeCategoriesPage = lazy(() => import('@/features/fees/pages/FeeCategoriesPage').then((m) => ({ default: m.FeeCategoriesPage })))
const FeeStructuresPage = lazy(() => import('@/features/fees/pages/FeeStructuresPage').then((m) => ({ default: m.FeeStructuresPage })))
const InvoicesListPage = lazy(() => import('@/features/fees/pages/InvoicesListPage').then((m) => ({ default: m.InvoicesListPage })))
const InvoiceDetailPage = lazy(() => import('@/features/fees/pages/InvoiceDetailPage').then((m) => ({ default: m.InvoiceDetailPage })))
const FeeReportsPage = lazy(() => import('@/features/fees/pages/FeeReportsPage').then((m) => ({ default: m.FeeReportsPage })))
const DesignationsPage = lazy(() => import('@/features/hr/pages/DesignationsPage').then((m) => ({ default: m.DesignationsPage })))
const LeaveTypesPage = lazy(() => import('@/features/hr/pages/LeaveTypesPage').then((m) => ({ default: m.LeaveTypesPage })))
const LeaveRequestsPage = lazy(() => import('@/features/hr/pages/LeaveRequestsPage').then((m) => ({ default: m.LeaveRequestsPage })))
const SalaryStructuresPage = lazy(() => import('@/features/hr/pages/SalaryStructuresPage').then((m) => ({ default: m.SalaryStructuresPage })))
const PayslipsPage = lazy(() => import('@/features/hr/pages/PayslipsPage').then((m) => ({ default: m.PayslipsPage })))
const BooksPage = lazy(() => import('@/features/library/pages/BooksPage').then((m) => ({ default: m.BooksPage })))
const BookIssuesPage = lazy(() => import('@/features/library/pages/BookIssuesPage').then((m) => ({ default: m.BookIssuesPage })))
const VehiclesPage = lazy(() => import('@/features/transport/pages/VehiclesPage').then((m) => ({ default: m.VehiclesPage })))
const RoutesPage = lazy(() => import('@/features/transport/pages/RoutesPage').then((m) => ({ default: m.RoutesPage })))
const StudentTransportAssignmentsPage = lazy(() =>
  import('@/features/transport/pages/StudentTransportAssignmentsPage').then((m) => ({ default: m.StudentTransportAssignmentsPage }))
)
const HostelsPage = lazy(() => import('@/features/hostel/pages/HostelsPage').then((m) => ({ default: m.HostelsPage })))
const HostelRoomsPage = lazy(() => import('@/features/hostel/pages/HostelRoomsPage').then((m) => ({ default: m.HostelRoomsPage })))
const HostelAllocationsPage = lazy(() => import('@/features/hostel/pages/HostelAllocationsPage').then((m) => ({ default: m.HostelAllocationsPage })))
const VisitorsPage = lazy(() => import('@/features/frontDesk/pages/VisitorsPage').then((m) => ({ default: m.VisitorsPage })))
const CertificateTemplatesPage = lazy(() => import('@/features/certificates/pages/CertificateTemplatesPage').then((m) => ({ default: m.CertificateTemplatesPage })))
const CertificatesPage = lazy(() => import('@/features/certificates/pages/CertificatesPage').then((m) => ({ default: m.CertificatesPage })))
const NoticeBoardPage = lazy(() => import('@/features/noticeBoard/pages/NoticeBoardPage').then((m) => ({ default: m.NoticeBoardPage })))
const AnnouncementsPage = lazy(() => import('@/features/communication/pages/AnnouncementsPage').then((m) => ({ default: m.AnnouncementsPage })))
const AttendanceReportPage = lazy(() => import('@/features/reports/pages/AttendanceReportPage').then((m) => ({ default: m.AttendanceReportPage })))
const AcademicReportPage = lazy(() => import('@/features/reports/pages/AcademicReportPage').then((m) => ({ default: m.AcademicReportPage })))
const EnrollmentReportPage = lazy(() => import('@/features/reports/pages/EnrollmentReportPage').then((m) => ({ default: m.EnrollmentReportPage })))
const OperationsReportPage = lazy(() => import('@/features/reports/pages/OperationsReportPage').then((m) => ({ default: m.OperationsReportPage })))
const PlatformSchoolsListPage = lazy(() => import('@/features/platform/pages/PlatformSchoolsListPage').then((m) => ({ default: m.PlatformSchoolsListPage })))
const PlatformSchoolDetailPage = lazy(() => import('@/features/platform/pages/PlatformSchoolDetailPage').then((m) => ({ default: m.PlatformSchoolDetailPage })))
const PlatformMetricsPage = lazy(() => import('@/features/platform/pages/PlatformMetricsPage').then((m) => ({ default: m.PlatformMetricsPage })))

export function AppRouter() {
  // Evaluated once per load, not reactive -- the hostname can't change without
  // a full navigation anyway. On the bare central domain, "/" is the public
  // marketing page instead of the dashboard (see routePaths.dashboard below,
  // guarded the other way so the two never both register for the same path).
  const isCentral = isCentralDomain()

  return (
    <Suspense fallback={<LoadingScreen />}>
      <Routes>
        {isCentral && <Route path={routePaths.dashboard} element={<LandingPage />} />}

        <Route path={routePaths.login} element={<LoginPage />} />
        <Route path={routePaths.platformLogin} element={<PlatformLoginPage />} />
        <Route path={routePaths.signup} element={<SignupPage />} />
        {/* Public, not ProtectedRoute — landed on unauthenticated (see
            SignupCompletePage's own docblock), it's what establishes the
            session in the first place. */}
        <Route path={routePaths.signupComplete} element={<SignupCompletePage />} />
        <Route path={routePaths.forgotPassword} element={<ForgotPasswordPage />} />
        <Route path={routePaths.resetPassword} element={<ResetPasswordPage />} />

        <Route element={<ProtectedRoute />}>
          {/* Outside RequireMfaSetup on purpose — the setup page must stay
              reachable for the account it's redirecting FROM. */}
          <Route path={routePaths.mfaSetup} element={<MfaSetupPage />} />

          <Route element={<RequireMfaSetup />}>
            <Route element={<AppShell />}>
              {!isCentral && <Route path={routePaths.dashboard} element={<DashboardPage />} />}
              <Route path={routePaths.help} element={<HelpGuidePage />} />

              <Route path={routePaths.parentChildren} element={<ParentChildrenPage />} />
              <Route path={routePaths.parentChildProfile()} element={<ParentChildProfilePage />} />

              <Route element={<PermissionRoute permissions={['users.view']} />}>
                <Route path={routePaths.users} element={<UsersListPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['roles.view']} />}>
                <Route path={routePaths.roles} element={<RolesListPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['settings.view']} />}>
                <Route path={routePaths.settings} element={<SettingsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['billing.view']} />}>
                <Route path={routePaths.settingsBilling} element={<BillingPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['audit-logs.view']} />}>
                <Route path={routePaths.auditLogs} element={<AuditLogsPage />} />
              </Route>

              {/* Self-service — no permission gate, every role can export
                  their own data (see DataExportController::indexSelf()). */}
              <Route path={routePaths.myDataExport} element={<MyDataExportPage />} />
              <Route element={<PermissionRoute permissions={['data-export.school']} />}>
                <Route path={routePaths.dataExports} element={<DataExportsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['academic-years.view']} />}>
                <Route path={routePaths.academicYears} element={<AcademicYearsPage />} />
                <Route path={routePaths.terms} element={<TermsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['academic-structure.view']} />}>
                <Route path={routePaths.departments} element={<DepartmentsPage />} />
                <Route path={routePaths.gradeLevels} element={<GradeLevelsPage />} />
                <Route path={routePaths.sections} element={<SectionsPage />} />
                <Route path={routePaths.subjects} element={<SubjectsPage />} />
                <Route path={routePaths.rooms} element={<RoomsPage />} />
                <Route path={routePaths.holidays} element={<HolidaysPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['timetable.view']} />}>
                <Route path={routePaths.timetable} element={<TimetablePage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['students.view']} />}>
                <Route path={routePaths.students} element={<StudentsListPage />} />
                <Route path={routePaths.studentProfile()} element={<StudentProfilePage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['students.create']} />}>
                <Route path={routePaths.studentAdmission} element={<StudentAdmissionPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['students.import']} />}>
                <Route path={routePaths.studentImport} element={<StudentImportPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['guardians.view']} />}>
                <Route path={routePaths.guardians} element={<GuardiansListPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['student-attendance.mark']} />}>
                <Route path={routePaths.attendanceTake} element={<TakeAttendancePage />} />
              </Route>
              <Route path={routePaths.attendanceStaff} element={<StaffAttendancePage />} />

              <Route element={<PermissionRoute permissions={['grading.view']} />}>
                <Route path={routePaths.gradingScales} element={<GradingScalesPage />} />
                <Route path={routePaths.examConfiguration} element={<ExamConfigurationPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['questions.view']} />}>
                <Route path={routePaths.questionBank} element={<QuestionBankPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['exams.view']} />}>
                <Route path={routePaths.exams} element={<ExamsListPage />} />
                <Route path={routePaths.examDetail()} element={<ExamDetailPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['exam-marks.enter']} />}>
                <Route path={routePaths.examSubjectMarks()} element={<MarksEntryPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['online-exams.configure']} />}>
                <Route path={routePaths.examSubjectOnlineTest()} element={<OnlineTestConfigPage />} />
              </Route>
              <Route path={routePaths.takeOnlineTest()} element={<TakeOnlineTestPage />} />
              <Route path={routePaths.myOnlineTests} element={<MyOnlineTestsPage />} />

              <Route element={<PermissionRoute permissions={['homework.view']} />}>
                <Route path={routePaths.homework} element={<HomeworkListPage />} />
                <Route path={routePaths.homeworkDetail()} element={<HomeworkDetailPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['fees.view']} />}>
                <Route path={routePaths.feeCategories} element={<FeeCategoriesPage />} />
                <Route path={routePaths.feeStructures} element={<FeeStructuresPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['invoices.view']} />}>
                <Route path={routePaths.invoices} element={<InvoicesListPage />} />
                <Route path={routePaths.invoiceDetail()} element={<InvoiceDetailPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['invoices.view-reports']} />}>
                <Route path={routePaths.feeReports} element={<FeeReportsPage />} />
              </Route>

              {/* Self-service — every staff member reaches these to apply for
                  their own leave / see their own payslips, so they aren't
                  behind a PermissionRoute; each page internally shows more
                  (everyone's records, approve/mark-paid actions) once the
                  viewer holds leave.view/leave.manage or payroll.view/.manage. */}
              <Route path={routePaths.leaveRequests} element={<LeaveRequestsPage />} />
              <Route path={routePaths.payslips} element={<PayslipsPage />} />

              <Route element={<PermissionRoute permissions={['designations.view']} />}>
                <Route path={routePaths.designations} element={<DesignationsPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['leave.view', 'leave.manage']} />}>
                <Route path={routePaths.leaveTypes} element={<LeaveTypesPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['payroll.view', 'payroll.manage']} />}>
                <Route path={routePaths.salaryStructures} element={<SalaryStructuresPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['library.view']} />}>
                <Route path={routePaths.books} element={<BooksPage />} />
                <Route path={routePaths.bookIssues} element={<BookIssuesPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['transport.view']} />}>
                <Route path={routePaths.vehicles} element={<VehiclesPage />} />
                <Route path={routePaths.routes} element={<RoutesPage />} />
                <Route path={routePaths.studentTransportAssignments} element={<StudentTransportAssignmentsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['hostel.view']} />}>
                <Route path={routePaths.hostels} element={<HostelsPage />} />
                <Route path={routePaths.hostelRooms} element={<HostelRoomsPage />} />
                <Route path={routePaths.hostelAllocations} element={<HostelAllocationsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['front-desk.view']} />}>
                <Route path={routePaths.visitors} element={<VisitorsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['certificates.create']} />}>
                <Route path={routePaths.certificateTemplates} element={<CertificateTemplatesPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['certificates.view']} />}>
                <Route path={routePaths.certificates} element={<CertificatesPage />} />
              </Route>

              {/* Self-service — the notice board is readable by every
                  authenticated user with no permission at all (see
                  NoticePolicy); the page itself shows create/edit/delete/
                  publish actions only once the viewer holds notice-board.*. */}
              <Route path={routePaths.noticeBoard} element={<NoticeBoardPage />} />

              <Route element={<PermissionRoute permissions={['communication.view']} />}>
                <Route path={routePaths.announcements} element={<AnnouncementsPage />} />
              </Route>

              <Route element={<PermissionRoute permissions={['student-attendance.view', 'staff-attendance.view']} />}>
                <Route path={routePaths.reportsAttendance} element={<AttendanceReportPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['exam-marks.view']} />}>
                <Route path={routePaths.reportsAcademic} element={<AcademicReportPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['students.view']} />}>
                <Route path={routePaths.reportsEnrollment} element={<EnrollmentReportPage />} />
              </Route>
              <Route element={<PermissionRoute permissions={['library.view', 'transport.view', 'hostel.view']} />}>
                <Route path={routePaths.reportsOperations} element={<OperationsReportPage />} />
              </Route>
            </Route>
          </Route>
        </Route>

        {/* Platform console — auth:platform guard, entirely separate from
            the tenant session/permissions above (see PlatformAuthContext). */}
        <Route element={<PlatformProtectedRoute />}>
          <Route path={routePaths.platformMfaSetup} element={<PlatformMfaSetupPage />} />

          <Route element={<RequirePlatformMfaSetup />}>
            <Route element={<PlatformShell />}>
              <Route path={routePaths.schools} element={<SchoolsListPage />} />
              <Route path={routePaths.platformSchools} element={<PlatformSchoolsListPage />} />
              <Route path={routePaths.platformSchoolDetail()} element={<PlatformSchoolDetailPage />} />
              <Route path={routePaths.platformMetrics} element={<PlatformMetricsPage />} />
            </Route>
          </Route>
        </Route>

        <Route path="*" element={<NotFound />} />
      </Routes>
    </Suspense>
  )
}
