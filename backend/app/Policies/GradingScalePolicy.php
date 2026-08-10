<?php

namespace App\Policies;

use App\Models\GradingScale;
use App\Models\User;

/**
 * Grading scales are infrequently-changed school configuration (like
 * Settings), so unlike most modules there's a single `grading.manage`
 * action rather than separate create/edit/delete permissions.
 */
class GradingScalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('grading.view');
    }

    public function view(User $user, GradingScale $gradingScale): bool
    {
        return $user->can('grading.view');
    }

    public function create(User $user): bool
    {
        return $user->can('grading.manage');
    }

    public function update(User $user, GradingScale $gradingScale): bool
    {
        return $user->can('grading.manage');
    }

    public function delete(User $user, GradingScale $gradingScale): bool
    {
        return $user->can('grading.manage');
    }
}
