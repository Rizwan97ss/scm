/** Every route path in the app, in one place, so nav links/redirects/tests never hand-type a URL string. */
export const routePaths = {
  login: '/login',
  platformLogin: '/platform-login',
  signup: '/signup',
  signupComplete: '/signup/complete',
  forgotPassword: '/forgot-password',
  resetPassword: '/reset-password',
  forbidden: '/forbidden',
  mfaSetup: '/mfa/setup',
  platformMfaSetup: '/platform-mfa/setup',

  dashboard: '/',
  help: '/help',

  users: '/users',
  roles: '/roles',
  settings: '/settings',
  settingsBilling: '/settings/billing',
  auditLogs: '/audit-logs',
  myDataExport: '/account/data-export',
  dataExports: '/data-exports',
  schools: '/schools',

  academicYears: '/academics/years',
  terms: '/academics/terms',
  departments: '/academics/departments',
  gradeLevels: '/academics/grade-levels',
  sections: '/academics/sections',
  subjects: '/academics/subjects',
  rooms: '/academics/rooms',
  holidays: '/academics/holidays',
  timetable: '/academics/timetable',

  students: '/students',
  studentAdmission: '/students/admission',
  studentProfile: (id: number | string = ':id') => `/students/${id}`,
  studentImport: '/students/import',

  guardians: '/guardians',

  attendanceTake: '/attendance/take',
  attendanceStaff: '/attendance/staff',

  gradingScales: '/exams/grading-scales',
  questionBank: '/exams/question-bank',
  exams: '/exams',
  examDetail: (id: number | string = ':examId') => `/exams/${id}`,
  examSubjectMarks: (examId: number | string = ':examId', examSubjectId: number | string = ':examSubjectId') => `/exams/${examId}/subjects/${examSubjectId}/marks`,
  examSubjectOnlineTest: (examId: number | string = ':examId', examSubjectId: number | string = ':examSubjectId') => `/exams/${examId}/subjects/${examSubjectId}/online-test`,
  takeOnlineTest: (examSubjectId: number | string = ':examSubjectId') => `/exams/take/${examSubjectId}`,
  myOnlineTests: '/exams/my-tests',

  homework: '/homework',
  homeworkDetail: (id: number | string = ':id') => `/homework/${id}`,

  feeCategories: '/fees/categories',
  feeStructures: '/fees/structures',
  invoices: '/fees/invoices',
  invoiceDetail: (id: number | string = ':id') => `/fees/invoices/${id}`,
  feeReports: '/fees/reports',

  designations: '/hr/designations',
  leaveTypes: '/hr/leave-types',
  leaveRequests: '/hr/leave-requests',
  salaryStructures: '/hr/salary-structures',
  payslips: '/hr/payslips',

  books: '/library/books',
  bookIssues: '/library/issues',

  vehicles: '/transport/vehicles',
  routes: '/transport/routes',
  studentTransportAssignments: '/transport/assignments',

  hostels: '/hostel/hostels',
  hostelRooms: '/hostel/rooms',
  hostelAllocations: '/hostel/allocations',

  visitors: '/front-desk/visitors',

  certificateTemplates: '/certificates/templates',
  certificates: '/certificates/issued',

  noticeBoard: '/notice-board',

  announcements: '/communication/announcements',

  reportsAttendance: '/reports/attendance',
  reportsAcademic: '/reports/academic-performance',
  reportsEnrollment: '/reports/enrollment',
  reportsOperations: '/reports/operations',

  parentChildren: '/parent/children',
  parentChildProfile: (id: number | string = ':id') => `/parent/children/${id}`,

  platformSchools: '/platform/schools',
  platformSchoolDetail: (id: number | string = ':id') => `/platform/schools/${id}`,
  platformMetrics: '/platform/metrics',
} as const
