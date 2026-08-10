<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') && $this->belongsToActorsSchool($user, $role);
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.edit')
            && $role->name !== 'Super Admin'
            && $this->belongsToActorsSchool($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete')
            && $role->name !== 'Super Admin'
            && $this->belongsToActorsSchool($user, $role);
    }

    /**
     * A role belongs to a school via its team_foreign_key (school_id). Super
     * Admin's own role lives under the reserved global team 0 and is handled
     * separately above; every other role must match the actor's own school.
     */
    private function belongsToActorsSchool(User $user, Role $role): bool
    {
        return $role->school_id === $user->school_id;
    }
}
