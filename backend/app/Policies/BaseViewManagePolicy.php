<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * For modules with a single `.manage` action rather than separate
 * create/edit/delete permissions — infrequently-changed or staff-only
 * config with no self-service angle (Library, Transport, Hostel, Front
 * Desk in Phase 10; mirrors the shape GradingScalePolicy established in
 * Phase 5, generalized here since four new modules need it at once).
 * Subclasses just declare `$permissionPrefix`.
 */
abstract class BaseViewManagePolicy
{
    protected string $permissionPrefix;

    public function viewAny(User $user): bool
    {
        return $user->can("{$this->permissionPrefix}.view");
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can("{$this->permissionPrefix}.view");
    }

    public function create(User $user): bool
    {
        return $user->can("{$this->permissionPrefix}.manage");
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can("{$this->permissionPrefix}.manage");
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can("{$this->permissionPrefix}.manage");
    }
}
