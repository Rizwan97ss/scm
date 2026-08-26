<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Owns leave-request state transitions the same way InvoiceService owns
 * invoice state — controllers never set status/reviewed_by directly.
 */
class LeaveService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, reason: string}  $data
     */
    public function apply(array $data, User $applicant): LeaveRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        return LeaveRequest::query()->create([
            'user_id' => $applicant->id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $start->diffInDays($end) + 1,
            'reason' => $data['reason'],
            'status' => LeaveStatus::Pending,
        ]);
    }

    public function cancel(LeaveRequest $leaveRequest): void
    {
        $leaveRequest->update(['status' => LeaveStatus::Cancelled]);
    }

    /**
     * @param  array{status: string, review_notes?: ?string}  $data
     */
    public function review(LeaveRequest $leaveRequest, array $data, User $reviewer): LeaveRequest
    {
        $leaveRequest->update([
            'status' => $data['status'],
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        if ($leaveRequest->status === LeaveStatus::Approved) {
            $this->markAttendanceForApprovedLeave($leaveRequest, $reviewer);
        }

        return $leaveRequest->refresh();
    }

    /**
     * Stamps every day of an approved leave as on_leave on the staff
     * member's attendance record — the enum value already existed
     * (AttendanceStatus::OnLeave, zero-weighted in presentWeight()) but
     * nothing wrote it automatically before this. A day that already has
     * an attendance record (e.g. approved after the fact) is corrected,
     * not duplicated — see AttendanceService::markOnLeave().
     */
    private function markAttendanceForApprovedLeave(LeaveRequest $leaveRequest, User $reviewer): void
    {
        $leaveRequest->loadMissing(['user', 'leaveType']);

        $date = $leaveRequest->start_date->copy();

        while ($date->lte($leaveRequest->end_date)) {
            $this->attendance->markOnLeave($leaveRequest->user, $date->copy(), $reviewer, "Leave: {$leaveRequest->leaveType->name}");
            $date->addDay();
        }
    }
}
