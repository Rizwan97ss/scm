import {
  CalendarCheck,
  CalendarDays,
  ClipboardCheck,
  ClipboardList,
  DoorOpen,
  FileQuestion,
  GraduationCap,
  LayoutDashboard,
  Layers,
  Building2,
  BarChart3,
  CreditCard,
  ScrollText,
  School,
  Settings,
  ShieldCheck,
  Sigma,
  Users,
  UsersRound,
  BookOpen,
  CalendarClock,
  NotebookPen,
  Receipt,
  Tags,
  Wallet,
  IdCard,
  CalendarOff,
  Banknote,
  Download,
  BookMarked,
  BookCopy,
  Bus,
  Map,
  UserCheck,
  Building,
  Bed,
  BedDouble,
  UserPlus,
  FileBadge,
  Award,
  Newspaper,
  Megaphone,
  LineChart,
  TrendingUp,
  Activity,
} from 'lucide-react'
import type { ComponentType } from 'react'
import { routePaths } from '@/routes/routePaths'

export interface NavItemConfig {
  label: string
  to: string
  icon: ComponentType<{ className?: string }>
  /** Any one of these permissions grants visibility; omit to show to every authenticated user. */
  permissions?: string[]
}

export interface NavGroupConfig {
  label: string
  items: NavItemConfig[]
}

export const NAV_GROUPS: NavGroupConfig[] = [
  {
    label: 'Overview',
    items: [{ label: 'Dashboard', to: routePaths.dashboard, icon: LayoutDashboard }],
  },
  {
    label: 'People',
    items: [
      { label: 'Students', to: routePaths.students, icon: GraduationCap, permissions: ['students.view'] },
      { label: 'Guardians', to: routePaths.guardians, icon: UsersRound, permissions: ['guardians.view'] },
      { label: 'Staff & Users', to: routePaths.users, icon: Users, permissions: ['users.view'] },
    ],
  },
  {
    label: 'Attendance',
    items: [
      { label: 'Take Attendance', to: routePaths.attendanceTake, icon: CalendarCheck, permissions: ['student-attendance.mark'] },
      { label: 'Staff Attendance', to: routePaths.attendanceStaff, icon: ClipboardCheck },
    ],
  },
  {
    label: 'Exams',
    items: [
      { label: 'Exams', to: routePaths.exams, icon: ClipboardList, permissions: ['exams.view'] },
      { label: 'Grading Scales', to: routePaths.gradingScales, icon: Sigma, permissions: ['grading.view'] },
      { label: 'Exam Configuration', to: routePaths.examConfiguration, icon: Settings, permissions: ['grading.view'] },
      { label: 'Question Bank', to: routePaths.questionBank, icon: FileQuestion, permissions: ['questions.view'] },
    ],
  },
  {
    label: 'Teaching',
    items: [{ label: 'Homework', to: routePaths.homework, icon: NotebookPen, permissions: ['homework.view'] }],
  },
  {
    label: 'Fees & Accounting',
    items: [
      { label: 'Invoices', to: routePaths.invoices, icon: Receipt, permissions: ['invoices.view'] },
      { label: 'Fee Structures', to: routePaths.feeStructures, icon: Wallet, permissions: ['fees.view'] },
      { label: 'Fee Categories', to: routePaths.feeCategories, icon: Tags, permissions: ['fees.view'] },
      { label: 'Fee Reports', to: routePaths.feeReports, icon: BarChart3, permissions: ['invoices.view-reports'] },
    ],
  },
  {
    label: 'HR & Payroll',
    items: [
      { label: 'Leave Requests', to: routePaths.leaveRequests, icon: CalendarOff },
      { label: 'Payslips', to: routePaths.payslips, icon: Banknote },
      { label: 'Designations', to: routePaths.designations, icon: IdCard, permissions: ['designations.view'] },
      { label: 'Leave Types', to: routePaths.leaveTypes, icon: CalendarOff, permissions: ['leave.view', 'leave.manage'] },
      { label: 'Salary Structures', to: routePaths.salaryStructures, icon: Wallet, permissions: ['payroll.view', 'payroll.manage'] },
    ],
  },
  {
    label: 'Library',
    items: [
      { label: 'Books', to: routePaths.books, icon: BookMarked, permissions: ['library.view'] },
      { label: 'Book Issues', to: routePaths.bookIssues, icon: BookCopy, permissions: ['library.view'] },
    ],
  },
  {
    label: 'Transport',
    items: [
      { label: 'Vehicles', to: routePaths.vehicles, icon: Bus, permissions: ['transport.view'] },
      { label: 'Routes', to: routePaths.routes, icon: Map, permissions: ['transport.view'] },
      { label: 'Student Assignments', to: routePaths.studentTransportAssignments, icon: UserCheck, permissions: ['transport.view'] },
    ],
  },
  {
    label: 'Hostel',
    items: [
      { label: 'Hostels', to: routePaths.hostels, icon: Building, permissions: ['hostel.view'] },
      { label: 'Rooms', to: routePaths.hostelRooms, icon: Bed, permissions: ['hostel.view'] },
      { label: 'Allocations', to: routePaths.hostelAllocations, icon: BedDouble, permissions: ['hostel.view'] },
    ],
  },
  {
    label: 'Front Desk',
    items: [{ label: 'Visitors', to: routePaths.visitors, icon: UserPlus, permissions: ['front-desk.view'] }],
  },
  {
    label: 'Certificates & ID Cards',
    items: [
      { label: 'Certificate Templates', to: routePaths.certificateTemplates, icon: FileBadge, permissions: ['certificates.create'] },
      { label: 'Issued Certificates', to: routePaths.certificates, icon: Award, permissions: ['certificates.view'] },
    ],
  },
  {
    label: 'Notice Board',
    items: [{ label: 'Notice Board', to: routePaths.noticeBoard, icon: Newspaper }],
  },
  {
    label: 'Communication',
    items: [{ label: 'Announcements', to: routePaths.announcements, icon: Megaphone, permissions: ['communication.view'] }],
  },
  {
    label: 'Reports & Analytics',
    items: [
      { label: 'Attendance Report', to: routePaths.reportsAttendance, icon: LineChart, permissions: ['student-attendance.view', 'staff-attendance.view'] },
      { label: 'Academic Performance', to: routePaths.reportsAcademic, icon: TrendingUp, permissions: ['exam-marks.view'] },
      { label: 'Enrollment Report', to: routePaths.reportsEnrollment, icon: GraduationCap, permissions: ['students.view'] },
      { label: 'Operations Report', to: routePaths.reportsOperations, icon: Activity, permissions: ['library.view', 'transport.view', 'hostel.view'] },
    ],
  },
  {
    label: 'Academics',
    items: [
      { label: 'Academic Years', to: routePaths.academicYears, icon: CalendarDays, permissions: ['academic-years.view'] },
      { label: 'Terms', to: routePaths.terms, icon: CalendarClock, permissions: ['academic-years.view'] },
      { label: 'Departments', to: routePaths.departments, icon: Building2, permissions: ['academic-structure.view'] },
      { label: 'Grade Levels', to: routePaths.gradeLevels, icon: Layers, permissions: ['academic-structure.view'] },
      { label: 'Sections', to: routePaths.sections, icon: School, permissions: ['academic-structure.view'] },
      { label: 'Subjects', to: routePaths.subjects, icon: BookOpen, permissions: ['academic-structure.view'] },
      { label: 'Rooms', to: routePaths.rooms, icon: DoorOpen, permissions: ['academic-structure.view'] },
      { label: 'Timetable', to: routePaths.timetable, icon: ClipboardList, permissions: ['timetable.view'] },
      { label: 'Holidays', to: routePaths.holidays, icon: CalendarDays, permissions: ['academic-structure.view'] },
    ],
  },
  {
    label: 'Administration',
    items: [
      { label: 'Roles & Permissions', to: routePaths.roles, icon: ShieldCheck, permissions: ['roles.view'] },
      { label: 'Settings', to: routePaths.settings, icon: Settings, permissions: ['settings.view'] },
      { label: 'Billing', to: routePaths.settingsBilling, icon: CreditCard, permissions: ['billing.view'] },
      { label: 'Audit Log', to: routePaths.auditLogs, icon: ScrollText, permissions: ['audit-logs.view'] },
      { label: 'Data Export', to: routePaths.dataExports, icon: Download, permissions: ['data-export.school'] },
    ],
  },
]

export const PARENT_NAV_GROUPS: NavGroupConfig[] = [
  {
    label: 'Overview',
    items: [
      { label: 'Dashboard', to: routePaths.dashboard, icon: LayoutDashboard },
      { label: 'My Children', to: routePaths.parentChildren, icon: GraduationCap },
      { label: 'Notice Board', to: routePaths.noticeBoard, icon: Newspaper },
      { label: 'Certificates', to: routePaths.certificates, icon: Award },
    ],
  },
]

export const STUDENT_NAV_GROUPS: NavGroupConfig[] = [
  {
    label: 'Overview',
    items: [
      { label: 'Dashboard', to: routePaths.dashboard, icon: LayoutDashboard },
      { label: 'Homework', to: routePaths.homework, icon: NotebookPen },
      { label: 'My Online Tests', to: routePaths.myOnlineTests, icon: FileQuestion },
      { label: 'My Fees', to: routePaths.invoices, icon: Receipt },
      { label: 'Notice Board', to: routePaths.noticeBoard, icon: Newspaper },
      { label: 'Certificates', to: routePaths.certificates, icon: Award },
    ],
  },
]

/**
 * Single source of truth for "which nav does this user see" — Sidebar.tsx
 * (desktop) and AppShell.tsx (mobile drawer) both call this instead of each
 * re-implementing the same role checks, after those two previously
 * disagreed (mobile gave Students the Parent nav — a bug, not a deliberate
 * difference). Super Admin has no branch here at all — it's a PlatformUser
 * now, on an entirely separate guard/shell (see PlatformShell), never a
 * tenant User this function's caller could even be handed.
 */
export function resolveNavGroups(hasRole: (...roles: string[]) => boolean): NavGroupConfig[] {
  if (hasRole('Student')) return STUDENT_NAV_GROUPS
  if (hasRole('Parent')) return PARENT_NAV_GROUPS
  return NAV_GROUPS
}
