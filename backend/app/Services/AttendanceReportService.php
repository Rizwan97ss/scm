<?php

namespace App\Services;

use App\Models\School;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance trend/breakdown reporting. Each section is computed only when
 * the caller holds the matching permission (student-attendance.view /
 * staff-attendance.view) — ReportController checks at least one is present
 * before calling in, this class fills in whichever the caller actually has.
 */
class AttendanceReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(School $school, User $user, Carbon $from, Carbon $to): array
    {
        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'student' => $user->can('student-attendance.view') ? $this->studentBreakdown($school, $from, $to) : null,
            'staff' => $user->can('staff-attendance.view') ? $this->staffBreakdown($school, $from, $to) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentBreakdown(School $school, Carbon $from, Carbon $to): array
    {
        $records = StudentAttendance::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereNull('timetable_period_id')
            ->get();

        $bySection = $records->groupBy('section_id')
            ->map(fn ($group) => $this->percentagePresent($group));

        $sectionNames = Section::query()->whereIn('id', $bySection->keys())->pluck('name', 'id');

        return [
            'overall_percentage' => $this->percentagePresent($records),
            'trend' => $this->monthlyTrend($records),
            'by_section' => $bySection->mapWithKeys(fn ($percentage, $sectionId) => [
                $sectionNames[$sectionId] ?? 'Unassigned' => $percentage,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffBreakdown(School $school, Carbon $from, Carbon $to): array
    {
        $records = StaffAttendance::query()
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        return [
            'overall_percentage' => $this->percentagePresent($records),
            'trend' => $this->monthlyTrend($records),
        ];
    }

    private function percentagePresent(Collection $records): ?float
    {
        $total = $records->count();
        if ($total === 0) {
            return null;
        }

        $presentEquivalent = $records->sum(fn ($record) => $record->status->presentWeight());

        return round(($presentEquivalent / $total) * 100, 2);
    }

    /**
     * @return array<int, array{month: string, percentage: ?float}>
     */
    private function monthlyTrend(Collection $records): array
    {
        return $records->groupBy(fn ($record) => $record->date->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group, $month) => ['month' => $month, 'percentage' => $this->percentagePresent($group)])
            ->values()
            ->all();
    }
}
