<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;

class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('guardians.view');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.view')
            && Guardian::query()->whereKey($guardian->id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('guardians.create');
    }

    public function import(User $user): bool
    {
        return $user->can('guardians.import');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.edit');
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.delete');
    }

    public function invite(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.edit');
    }
}
