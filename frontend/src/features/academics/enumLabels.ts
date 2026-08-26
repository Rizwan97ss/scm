import type { HolidayType, RoomType } from '@/types/enums'

export const HOLIDAY_TYPE_TRANSLATION_KEY: Record<HolidayType, string> = {
  public: 'academics.holidayTypePublic',
  school_specific: 'academics.holidayTypeSchoolSpecific',
}

export const ROOM_TYPE_TRANSLATION_KEY: Record<RoomType, string> = {
  classroom: 'academics.roomTypeClassroom',
  lab: 'academics.roomTypeLab',
  hall: 'academics.roomTypeHall',
  other: 'academics.roomTypeOther',
}
