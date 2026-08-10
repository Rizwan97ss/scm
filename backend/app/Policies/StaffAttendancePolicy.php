<?php

namespace App\Policies;

use App\Models\StaffAttendance;
use App\Models\User;

/**
 * Unlike student attendance, every staff member can always see their OWN
 * attendance record regardless of the `staff-attendance.view` permission —
 * that permission gates seeing *other* staff's records (HR/Admin/Principal),
 * not self-visibility. viewAny is intentionally permissive for the same
 * reason: StaffAttendanceController@index scopes the query to the caller's
 * own records when they lack the blanket permission, rather than denying
 * the endpoint outright.
 */
class StaffAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StaffAttendance $attendance): bool
    {
        if ($attendance->user_id === $user->id) {
            return true;
        }

        return $user->can('staff-attendance.view');
    }

    public function mark(User $user): bool
    {
        return $user->can('staff-attendance.mark');
    }

    /**
     * Self check-in/check-out is a distinct, narrower ability from bulk marking
     * others' attendance — any staff member (not Student/Parent) may record
     * their own arrival/departure without holding staff-attendance.mark.
     */
    public function selfCheckInOut(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, StaffAttendance $attendance): bool
    {
        return $user->can('staff-attendance.edit');
    }

    public function export(User $user): bool
    {
        return $user->can('staff-attendance.export');
    }
}
