<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\HostelRoom;
use App\Models\School;
use App\Models\StudentTransportAssignment;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Library/Transport/Hostel at-a-glance counts. Each section is computed only
 * when the caller holds the matching module's .view permission — same
 * "compute only what you're allowed to see" shape as AttendanceReportService.
 */
class OperationsReportService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(School $school, User $user): array
    {
        return [
            'library' => $user->can('library.view') ? $this->library($school) : null,
            'transport' => $user->can('transport.view') ? $this->transport($school) : null,
            'hostel' => $user->can('hostel.view') ? $this->hostel($school) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function library(School $school): array
    {
        return [
            'total_books' => Book::query()->where('school_id', $school->id)->count(),
            'issued_this_month' => BookIssue::query()->where('school_id', $school->id)->whereDate('issue_date', '>=', now()->startOfMonth())->count(),
            'currently_overdue' => BookIssue::query()->where('school_id', $school->id)->where('status', 'issued')->whereDate('due_date', '<', now())->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transport(School $school): array
    {
        return [
            'vehicle_count' => Vehicle::query()->where('school_id', $school->id)->where('is_active', true)->count(),
            'students_assigned' => StudentTransportAssignment::query()->where('school_id', $school->id)->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hostel(School $school): array
    {
        $rooms = HostelRoom::query()->where('school_id', $school->id)->where('is_active', true)->withCount(['allocations' => fn ($q) => $q->where('status', 'allocated')])->get();

        $totalCapacity = $rooms->sum('capacity');
        $totalOccupied = $rooms->sum('allocations_count');

        return [
            'room_count' => $rooms->count(),
            'total_capacity' => $totalCapacity,
            'total_occupied' => $totalOccupied,
            'occupancy_percentage' => $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 2) : null,
        ];
    }
}
