<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Roles broad enough that granting one is a privileged act in its own right,
     * gated the same way RolePolicy gates editing what a role can do. Without this,
     * anyone holding only users.create/users.edit -- e.g. HR Staff, whose default
     * permission set is deliberately scoped to day-to-day staff records, not admin
     * control (see SchoolProvisioningService::SCHOOL_SCOPED_ROLE_PERMISSIONS) --
     * could create or promote an account straight to full tenant admin just by
     * including "School Admin" in the roles array, bypassing the roles.create/
     * roles.edit gate RolePolicy exists specifically to enforce.
     */
    private const RESTRICTED_ROLES = ['School Admin'];

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view') || $user->id === $model->id;
    }

    /** @param  string[]  $roles */
    public function create(User $user, array $roles = []): bool
    {
        return $user->can('users.create') && $this->canAssignRoles($user, $roles);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.edit') || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete') && $user->id !== $model->id;
    }

    /** @param  string[]  $roles */
    public function manageRoles(User $user, User $model, array $roles = []): bool
    {
        return $user->can('users.edit') && $user->id !== $model->id && $this->canAssignRoles($user, $roles);
    }

    /** @param  string[]  $roles */
    private function canAssignRoles(User $user, array $roles): bool
    {
        if (array_intersect($roles, self::RESTRICTED_ROLES) === []) {
            return true;
        }

        // roles.edit covers the default School Admin permission set already; the
        // hasRole fallback only matters if a school customizes its own School
        // Admin role's permissions and accidentally strips roles.edit from it --
        // without it that would be a self-inflicted lockout with no way back in.
        return $user->can('roles.edit') || $user->hasRole('School Admin');
    }
}
