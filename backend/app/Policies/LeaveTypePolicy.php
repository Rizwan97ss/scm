<?php

namespace App\Policies;

use App\Models\LeaveType;
use App\Models\User;

/**
 * LeaveType is config (Casual Leave, Sick Leave, ...), not a request — it
 * doesn't get its own permission module. Viewing the list is unrestricted
 * to any staff member (not Student/Parent): applying for leave means
 * picking a leave type first, so every staff member needs read access to
 * this catalog the same way they can always submit their own
 * LeaveRequest without holding leave.view/leave.manage — see
 * LeaveRequestPolicy. Managing it (create/edit/delete) needs leave.manage,
 * same as approving/rejecting an actual request.
 */
class LeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, LeaveType $leaveType): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('leave.manage');
    }

    public function update(User $user, LeaveType $leaveType): bool
    {
        return $user->can('leave.manage');
    }

    public function delete(User $user, LeaveType $leaveType): bool
    {
        return $this->update($user, $leaveType);
    }
}
