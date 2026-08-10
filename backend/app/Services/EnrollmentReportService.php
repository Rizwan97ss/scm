<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollmentHistory;

class EnrollmentReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(School $school): array
    {
        $from = now()->subMonths(5)->startOfMonth();

        $historyEntries = StudentEnrollmentHistory::query()
            ->whereHas('student', fn ($q) => $q->where('school_id', $school->id))
            ->whereDate('effective_date', '>=', $from)
            ->get();

        $trend = $historyEntries
            ->groupBy(fn (StudentEnrollmentHistory $entry) => $entry->effective_date->format('Y-m'))
            ->sortKeys()
            ->map(fn ($group, $month) => [
                'month' => $month,
                'admissions' => $group->where('action', 'admission')->count(),
                'withdrawals' => $group->whereIn('action', ['withdrawal', 'transfer'])->count(),
                'graduations' => $group->where('action', 'graduation')->count(),
            ])
            ->values();

        $byGrade = Student::query()
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->with('currentGradeLevel')
            ->get()
            ->groupBy(fn (Student $student) => $student->currentGradeLevel?->name ?? 'Unassigned')
            ->map->count();

        return [
            'trend' => $trend->all(),
            'active_by_grade' => $byGrade,
            'active_total' => Student::query()->where('school_id', $school->id)->where('status', 'active')->count(),
        ];
    }
}
