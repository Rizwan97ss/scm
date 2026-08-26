<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Excused = 'excused';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Absent => 'Absent',
            self::Late => 'Late',
            self::HalfDay => 'Half Day',
            self::Excused => 'Excused',
            self::OnLeave => 'On Leave',
        };
    }

    /**
     * Weight toward an attendance percentage. Present/Late count fully, Half Day
     * counts as half; Absent/Excused/OnLeave count as 0 — this is a deliberate
     * simplification (a school that wants excused absences excluded from the
     * denominator entirely, rather than counted against it, would need that as
     * a per-school setting, which isn't built yet).
     */
    public function presentWeight(): float
    {
        return match ($this) {
            self::Present, self::Late => 1.0,
            self::HalfDay => 0.5,
            self::Absent, self::Excused, self::OnLeave => 0.0,
        };
    }
}
