<?php

namespace App\Policies;

use App\Models\StudentAttendance;
use App\Models\User;

class StudentAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('student-attendance.view');
    }

    public function view(User $user, StudentAttendance $attendance): bool
    {
        return $user->can('student-attendance.view')
            && StudentAttendance::query()->whereKey($attendance->id)->visibleTo($user)->exists();
    }

    /**
     * Marking is a bulk operation checked against the target section rather than
     * a single model instance — see StudentAttendanceController@store, which also
     * verifies the acting Teacher/Class Teacher actually teaches that section.
     */
    public function mark(User $user): bool
    {
        return $user->can('student-attendance.mark');
    }

    public function update(User $user, StudentAttendance $attendance): bool
    {
        return $user->can('student-attendance.edit')
            && StudentAttendance::query()->whereKey($attendance->id)->visibleTo($user)->exists();
    }

    public function export(User $user): bool
    {
        return $user->can('student-attendance.export');
    }
}
