/**
 * Central query key factory. Keeping every module's keys here (rather than
 * inline in each hook) makes cross-module invalidation (e.g. promoting a
 * student should refresh both the student list and its enrollment history)
 * easy to audit in one place.
 */
export const queryKeys = {
  me: ['me'] as const,
  platformMe: ['platform-me'] as const,
  plans: ['plans'] as const,
  publicSettings: (school?: string) => ['settings', 'public', school ?? null] as const,
  settings: ['settings'] as const,

  schools: (params?: unknown) => ['schools', params] as const,
  school: (id: number) => ['schools', id] as const,

  users: (params?: unknown) => ['users', params] as const,
  user: (id: number) => ['users', id] as const,

  roles: (params?: unknown) => ['roles', params] as const,
  role: (id: number) => ['roles', id] as const,
  permissions: ['permissions'] as const,

  auditLogs: (params?: unknown) => ['audit-logs', params] as const,

  dataExportsSelf: ['data-exports', 'self'] as const,
  dataExportsSchool: ['data-exports', 'school'] as const,

  academicYears: (params?: unknown) => ['academic-years', params] as const,
  academicYear: (id: number) => ['academic-years', id] as const,
  terms: (params?: unknown) => ['terms', params] as const,
  departments: (params?: unknown) => ['departments', params] as const,
  gradeLevels: (params?: unknown) => ['grade-levels', params] as const,
  sections: (params?: unknown) => ['sections', params] as const,
  subjects: (params?: unknown) => ['subjects', params] as const,
  rooms: (params?: unknown) => ['rooms', params] as const,
  holidays: (params?: unknown) => ['holidays', params] as const,
  timetablePeriods: (params?: unknown) => ['timetable-periods', params] as const,
  timetable: (params?: unknown) => ['timetable', params] as const,
  classSubjectTeachers: (params?: unknown) => ['class-subject-teachers', params] as const,

  students: (params?: unknown) => ['students', params] as const,
  student: (id: number) => ['students', id] as const,
  studentDocuments: (id: number) => ['students', id, 'documents'] as const,
  studentEnrollmentHistory: (id: number) => ['students', id, 'enrollment-history'] as const,
  guardians: (params?: unknown) => ['guardians', params] as const,
  guardian: (id: number) => ['guardians', id] as const,

  dashboardSummary: ['dashboard', 'summary'] as const,
  parentChildren: ['parent', 'children'] as const,
  parentChildProfile: (id: number) => ['parent', 'children', id] as const,
  parentChildAttendance: (id: number, params?: unknown) => ['parent', 'children', id, 'attendance', params] as const,
  parentChildExams: (id: number) => ['parent', 'children', id, 'exams'] as const,
  parentChildReportCard: (id: number, examId: number) => ['parent', 'children', id, 'report-card', examId] as const,
  parentChildTermResult: (id: number, termId: number) => ['parent', 'children', id, 'term-result', termId] as const,
  parentChildHomework: (id: number) => ['parent', 'children', id, 'homework'] as const,
  parentChildRemarks: (id: number) => ['parent', 'children', id, 'remarks'] as const,

  studentAttendance: (params?: unknown) => ['attendance', 'students', params] as const,
  studentAttendanceSummary: (studentId: number, params?: unknown) => ['attendance', 'students', 'summary', studentId, params] as const,
  sectionAttendanceSummary: (sectionId: number, params?: unknown) => ['attendance', 'students', 'section-summary', sectionId, params] as const,
  staffAttendance: (params?: unknown) => ['attendance', 'staff', params] as const,
  staffAttendanceSummary: (userId?: number, params?: unknown) => ['attendance', 'staff', 'summary', userId ?? null, params] as const,

  gradingScales: (params?: unknown) => ['grading-scales', params] as const,
  gradingScale: (id: number) => ['grading-scales', id] as const,
  examTypes: (params?: unknown) => ['exam-types', params] as const,
  assessmentComponentTypes: (params?: unknown) => ['assessment-component-types', params] as const,
  questions: (params?: unknown) => ['questions', params] as const,
  question: (id: number) => ['questions', id] as const,
  exams: (params?: unknown) => ['exams', params] as const,
  exam: (id: number) => ['exams', id] as const,
  examMarks: (examSubjectId: number) => ['exams', 'subjects', examSubjectId, 'marks'] as const,
  groupResult: (examId: number, groupId: number, studentId: number) => ['exams', examId, 'groups', groupId, 'result', studentId] as const,
  examTimetable: (examId: number, params: { sectionId?: number; studentId?: number }) => ['exams', examId, 'timetable', params] as const,
  reportCard: (examId: number, studentId: number) => ['exams', examId, 'report-card', studentId] as const,
  termResult: (termId: number, studentId: number) => ['terms', termId, 'result', studentId] as const,
  onlineTestAttempt: (attemptId: number) => ['online-test-attempts', attemptId] as const,
  myOnlineTests: ['online-tests', 'mine'] as const,
  onlineTestQuestions: (examSubjectId: number) => ['exam-subjects', examSubjectId, 'online-test-questions'] as const,

  homework: (params?: unknown) => ['homework', params] as const,
  homeworkItem: (id: number) => ['homework', id] as const,
  homeworkRoster: (id: number) => ['homework', id, 'submissions'] as const,
  studentRemarks: (params?: unknown) => ['student-remarks', params] as const,

  feeCategories: (params?: unknown) => ['fee-categories', params] as const,
  feeStructures: (params?: unknown) => ['fee-structures', params] as const,
  feeStructure: (id: number) => ['fee-structures', id] as const,
  studentFeeAssignments: (params?: unknown) => ['student-fee-assignments', params] as const,
  invoices: (params?: unknown) => ['invoices', params] as const,
  invoice: (id: number) => ['invoices', id] as const,
  payments: (params?: unknown) => ['payments', params] as const,
  feeStatement: (studentId: number) => ['students', studentId, 'fee-statement'] as const,
  parentChildInvoices: (id: number) => ['parent', 'children', id, 'invoices'] as const,
  feeReportsCollectionSummary: (params?: unknown) => ['fee-reports', 'collection-summary', params] as const,
  feeReportsOutstandingDues: ['fee-reports', 'outstanding-dues'] as const,

  designations: (params?: unknown) => ['designations', params] as const,
  leaveTypes: (params?: unknown) => ['leave-types', params] as const,
  leaveRequests: (params?: unknown) => ['leave-requests', params] as const,
  salaryStructures: (params?: unknown) => ['salary-structures', params] as const,
  payslips: (params?: unknown) => ['payslips', params] as const,

  books: (params?: unknown) => ['books', params] as const,
  bookIssues: (params?: unknown) => ['book-issues', params] as const,

  vehicles: (params?: unknown) => ['vehicles', params] as const,
  routes: (params?: unknown) => ['routes', params] as const,
  studentTransportAssignments: (params?: unknown) => ['student-transport-assignments', params] as const,

  hostels: (params?: unknown) => ['hostels', params] as const,
  hostelRooms: (params?: unknown) => ['hostel-rooms', params] as const,
  hostelAllocations: (params?: unknown) => ['hostel-allocations', params] as const,

  visitors: (params?: unknown) => ['visitors', params] as const,

  certificateTemplates: (params?: unknown) => ['certificate-templates', params] as const,
  certificates: (params?: unknown) => ['certificates', params] as const,

  notices: (params?: unknown) => ['notices', params] as const,

  announcements: (params?: unknown) => ['announcements', params] as const,
  notifications: (params?: unknown) => ['notifications', params] as const,

  reportsAttendance: (params?: unknown) => ['reports', 'attendance', params] as const,
  reportsAcademicPerformance: ['reports', 'academic-performance'] as const,
  reportsEnrollment: ['reports', 'enrollment'] as const,
  reportsOperations: ['reports', 'operations'] as const,

  search: (query: string) => ['search', query] as const,

  platformSchools: (params?: unknown) => ['platform', 'schools', params] as const,
  platformSchool: (id: number) => ['platform', 'schools', id] as const,
  platformMetrics: ['platform', 'metrics'] as const,

  billing: ['billing'] as const,
}
