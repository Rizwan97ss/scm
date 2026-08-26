<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Section;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\TimetablePeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Marking is always an upsert keyed on (student|user, date[, period]), never a
 * plain create — re-submitting a roster (a teacher fixing a mis-click before
 * end of day) corrects the existing row instead of creating a duplicate. This
 * is also what actually enforces "one daily record per student per day" (the
 * DB unique index alone doesn't, since NULL timetable_period_id isn't unique
 * across rows in MySQL/SQLite — see the migration).
 *
 * Every match/filter on the `date` column goes through whereDate(), never a
 * plain where('date', $string) or whereBetween('date', [...]). Eloquent's
 * `date` cast normalizes the value to a full "Y-m-d H:i:s" string on WRITE
 * (MySQL's native DATE column silently truncates the time part back off, but
 * SQLite's dynamic typing does not), while an app-built "Y-m-d" string used
 * in a plain where() is never coerced the same way — so exact-string matching
 * against a date column is unreliable across drivers. whereDate() compares
 * via the date portion only on both sides and is safe on both engines.
 */
class AttendanceService
{
    /**
     * @param  array<int, array{student_id: int, status: string, remarks?: ?string}>  $entries
     */
    public function markStudentsBulk(Section $section, Carbon $date, ?TimetablePeriod $period, array $entries, User $markedBy): EloquentCollection
    {
        return DB::transaction(function () use ($section, $date, $period, $entries, $markedBy) {
            $records = collect($entries)->map(function (array $entry) use ($section, $date, $period, $markedBy) {
                $query = StudentAttendance::query()
                    ->where('student_id', $entry['student_id'])
                    ->whereDate('date', $date)
                    ->where('timetable_period_id', $period?->id);

                return $this->upsert($query, [
                    'student_id' => $entry['student_id'],
                    'section_id' => $section->id,
                    'academic_year_id' => $section->academic_year_id,
                    'timetable_period_id' => $period?->id,
                    'date' => $date->toDateString(),
                    'status' => $entry['status'],
                    'remarks' => $entry['remarks'] ?? null,
                    'marked_by' => $markedBy->id,
                ], StudentAttendance::class);
            });

            return EloquentCollection::make($records->all());
        });
    }

    public function correctStudentAttendance(StudentAttendance $attendance, string $status, ?string $remarks, User $markedBy): StudentAttendance
    {
        $attendance->update(['status' => $status, 'remarks' => $remarks, 'marked_by' => $markedBy->id]);

        return $attendance->refresh();
    }

    public function studentSummary(Student $student, Carbon $from, Carbon $to): array
    {
        $records = StudentAttendance::query()
            ->where('student_id', $student->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereNull('timetable_period_id')
            ->get();

        return $this->summarize($records);
    }

    public function sectionDailySummary(Section $section, Carbon $date, ?TimetablePeriod $period = null): array
    {
        $records = StudentAttendance::query()
            ->where('section_id', $section->id)
            ->whereDate('date', $date)
            ->when(
                $period,
                fn ($q) => $q->where('timetable_period_id', $period->id),
                fn ($q) => $q->whereNull('timetable_period_id'),
            )
            ->get();

        return $this->summarize($records);
    }

    /**
     * @param  array<int, array{user_id: int, status: string, remarks?: ?string}>  $entries
     */
    public function markStaffBulk(array $entries, Carbon $date, User $markedBy): EloquentCollection
    {
        return DB::transaction(function () use ($entries, $date, $markedBy) {
            $records = collect($entries)->map(function (array $entry) use ($date, $markedBy) {
                $query = StaffAttendance::query()
                    ->where('user_id', $entry['user_id'])
                    ->whereDate('date', $date);

                return $this->upsert($query, [
                    'user_id' => $entry['user_id'],
                    'date' => $date->toDateString(),
                    'status' => $entry['status'],
                    'remarks' => $entry['remarks'] ?? null,
                    'marked_by' => $markedBy->id,
                ], StaffAttendance::class);
            });

            return EloquentCollection::make($records->all());
        });
    }

    public function checkIn(User $staff): StaffAttendance
    {
        $today = now();

        $query = StaffAttendance::query()->where('user_id', $staff->id)->whereDate('date', $today);

        return $this->upsert($query, [
            'user_id' => $staff->id,
            'date' => $today->toDateString(),
            'status' => AttendanceStatus::Present,
            'check_in_time' => $today->format('H:i:s'),
            'marked_by' => $staff->id,
        ], StaffAttendance::class);
    }

    public function checkOut(User $staff): StaffAttendance
    {
        $attendance = StaffAttendance::query()
            ->where('user_id', $staff->id)
            ->whereDate('date', now())
            ->firstOrFail();

        $attendance->update(['check_out_time' => now()->format('H:i:s')]);

        return $attendance->refresh();
    }

    public function correctStaffAttendance(StaffAttendance $attendance, string $status, ?string $remarks, User $markedBy): StaffAttendance
    {
        $attendance->update(['status' => $status, 'remarks' => $remarks, 'marked_by' => $markedBy->id]);

        return $attendance->refresh();
    }

    /**
     * Called by LeaveService when a leave request is approved — stamps every
     * day in the range as on_leave, reusing this class's own whereDate()-safe
     * upsert() so an already-marked day (e.g. a half-day recorded before the
     * leave was approved) is corrected rather than duplicated.
     */
    public function markOnLeave(User $staff, Carbon $date, User $markedBy, ?string $remarks = null): StaffAttendance
    {
        $query = StaffAttendance::query()->where('user_id', $staff->id)->whereDate('date', $date);

        return $this->upsert($query, [
            'user_id' => $staff->id,
            'date' => $date->toDateString(),
            'status' => AttendanceStatus::OnLeave,
            'remarks' => $remarks,
            'marked_by' => $markedBy->id,
        ], StaffAttendance::class);
    }

    public function staffSummary(User $staff, Carbon $from, Carbon $to): array
    {
        $records = StaffAttendance::query()
            ->where('user_id', $staff->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        return $this->summarize($records);
    }

    /**
     * Manual find-then-update-or-create, keyed by an already-built whereDate()
     * query rather than Eloquent's updateOrCreate() — that helper builds its
     * search WHERE from the raw attributes array (plain where(), not
     * whereDate()), which is exactly the exact-string-match trap this class's
     * docblock describes.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    private function upsert(Builder $query, array $attributes, string $modelClass): Model
    {
        $existing = $query->first();

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return $modelClass::query()->create($attributes);
    }

    /**
     * @param  Collection<int, StudentAttendance|StaffAttendance>  $records
     */
    private function summarize(Collection $records): array
    {
        $total = $records->count();
        $presentEquivalent = $records->sum(fn ($record) => $record->status->presentWeight());

        return [
            'total_marked' => $total,
            'present_equivalent' => $presentEquivalent,
            'percentage' => $total > 0 ? round(($presentEquivalent / $total) * 100, 2) : null,
            'counts' => collect(AttendanceStatus::cases())
                ->mapWithKeys(fn (AttendanceStatus $status) => [
                    $status->value => $records->filter(fn ($record) => $record->status === $status)->count(),
                ])
                ->all(),
        ];
    }
}
