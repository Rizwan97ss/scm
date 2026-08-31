<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared policy for straightforward CRUD modules whose authorization is just
 * "does the user hold the {prefix}.{action} permission" with no per-record
 * ownership scoping (school scoping is already handled by BelongsToSchool).
 * Subclasses only need to set $permissionPrefix.
 */
abstract class BaseModulePolicy
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
        return $user->can("{$this->permissionPrefix}.create");
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can("{$this->permissionPrefix}.edit");
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can("{$this->permissionPrefix}.delete");
    }

    public function import(User $user): bool
    {
        return $user->can("{$this->permissionPrefix}.import");
    }
}
