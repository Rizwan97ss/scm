<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

/**
 * Mirrors StaffAttendancePolicy's shape exactly: viewAny is permissive
 * (LeaveRequestController@index scopes to the caller's own records unless
 * they hold leave.view, rather than denying the endpoint outright), and
 * every staff member always sees/creates/cancels their OWN requests
 * regardless of that permission — leave.view/leave.manage only expand
 * reach to *other* staff's requests.
 */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->user_id === $user->id) {
            return true;
        }

        return $user->can('leave.view');
    }

    /** Any staff member (not Student/Parent) may submit a leave request for themselves. */
    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    /** A pending request may be withdrawn by the person who filed it — not once it's been reviewed. */
    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->user_id === $user->id && $leaveRequest->status->value === 'pending';
    }

    public function review(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.manage');
    }
}
