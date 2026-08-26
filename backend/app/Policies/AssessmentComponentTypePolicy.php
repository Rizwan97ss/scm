<?php

namespace App\Policies;

use App\Models\AssessmentComponentType;
use App\Models\User;

/** Same shape as GradingScalePolicy — infrequently-changed school configuration, one combined `grading.manage` action rather than separate create/edit/delete. */
class AssessmentComponentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('grading.view');
    }

    public function view(User $user, AssessmentComponentType $assessmentComponentType): bool
    {
        return $user->can('grading.view');
    }

    public function create(User $user): bool
    {
        return $user->can('grading.manage');
    }

    public function update(User $user, AssessmentComponentType $assessmentComponentType): bool
    {
        return $user->can('grading.manage');
    }

    public function delete(User $user, AssessmentComponentType $assessmentComponentType): bool
    {
        return $user->can('grading.manage');
    }
}
