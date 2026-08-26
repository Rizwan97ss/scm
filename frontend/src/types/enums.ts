/**
 * Mirrors the backend's App\Enums\* classes. These are protocol-level values
 * (the API request/response contract), not admin-configurable data, so they
 * live here as the single frontend source of truth rather than being
 * hardcoded inline wherever a status badge or select option is rendered.
 */

export const GENDERS = ['male', 'female', 'other'] as const
export type Gender = (typeof GENDERS)[number]
export const GENDER_LABEL_KEYS: Record<Gender, string> = {
  male: 'enums.genderMale',
  female: 'enums.genderFemale',
  other: 'enums.genderOther',
}

export const USER_STATUSES = ['active', 'inactive', 'suspended'] as const
export type UserStatus = (typeof USER_STATUSES)[number]
export const USER_STATUS_LABEL_KEYS: Record<UserStatus, string> = {
  active: 'common.active',
  inactive: 'common.inactive',
  suspended: 'enums.userStatusSuspended',
}

export const STUDENT_STATUSES = ['active', 'transferred_out', 'withdrawn', 'graduated', 'alumni'] as const
export type StudentStatus = (typeof STUDENT_STATUSES)[number]
export const STUDENT_STATUS_LABEL_KEYS: Record<StudentStatus, string> = {
  active: 'common.active',
  transferred_out: 'enums.studentStatusTransferredOut',
  withdrawn: 'enums.studentStatusWithdrawn',
  graduated: 'enums.studentStatusGraduated',
  alumni: 'enums.studentStatusAlumni',
}

export const ENROLLMENT_ACTIONS = [
  'admission',
  'promotion',
  'transfer_in',
  'transfer_out',
  'withdrawal',
  'graduation',
  'reactivation',
] as const
export type EnrollmentAction = (typeof ENROLLMENT_ACTIONS)[number]
export const ENROLLMENT_ACTION_LABEL_KEYS: Record<EnrollmentAction, string> = {
  admission: 'enums.enrollmentAdmission',
  promotion: 'enums.enrollmentPromotion',
  transfer_in: 'enums.enrollmentTransferIn',
  transfer_out: 'enums.enrollmentTransferOut',
  withdrawal: 'enums.enrollmentWithdrawal',
  graduation: 'enums.enrollmentGraduation',
  reactivation: 'enums.enrollmentReactivation',
}

export const GUARDIAN_RELATIONSHIPS = ['father', 'mother', 'guardian', 'other'] as const
export type GuardianRelationship = (typeof GUARDIAN_RELATIONSHIPS)[number]
export const GUARDIAN_RELATIONSHIP_LABEL_KEYS: Record<GuardianRelationship, string> = {
  father: 'enums.guardianFather',
  mother: 'enums.guardianMother',
  guardian: 'entities.guardian',
  other: 'enums.guardianOther',
}

export const ROOM_TYPES = ['classroom', 'lab', 'hall', 'other'] as const
export type RoomType = (typeof ROOM_TYPES)[number]

export const HOLIDAY_TYPES = ['public', 'school_specific'] as const
export type HolidayType = (typeof HOLIDAY_TYPES)[number]

export const ACADEMIC_YEAR_STATUSES = ['upcoming', 'active', 'closed'] as const
export type AcademicYearStatus = (typeof ACADEMIC_YEAR_STATUSES)[number]

export const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'half_day', 'excused', 'on_leave'] as const
export type AttendanceStatus = (typeof ATTENDANCE_STATUSES)[number]

/** Carbon/PHP day-of-week numbering: 0 = Sunday ... 6 = Saturday. Labels are localized at the call site via i18next's common.daysOfWeek array (same index order). */
export const DAY_OF_WEEK_VALUES = [0, 1, 2, 3, 4, 5, 6] as const
