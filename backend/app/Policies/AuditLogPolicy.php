<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit-logs.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('audit-logs.view');
    }

    /** Undoing an import deletes data — deliberately a separate, stricter permission than just viewing the audit trail. */
    public function undo(User $user): bool
    {
        return $user->can('audit-logs.manage');
    }
}
