<?php

namespace App\Policies;

use App\Models\AppNotification;
use App\Models\User;

/**
 * Your notification inbox is always your own — same self-service shape as
 * leave requests/payslips. No permission exists for "view your own
 * notifications" because there's nothing to gate: every authenticated user
 * has exactly one inbox, theirs.
 */
class AppNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AppNotification $appNotification): bool
    {
        return $appNotification->user_id === $user->id;
    }

    public function update(User $user, AppNotification $appNotification): bool
    {
        return $appNotification->user_id === $user->id;
    }
}
