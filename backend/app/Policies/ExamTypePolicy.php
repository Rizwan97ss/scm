<?php

namespace App\Policies;

use App\Models\ExamType;
use App\Models\User;

/** Same shape as GradingScalePolicy — infrequently-changed school configuration, one combined `grading.manage` action rather than separate create/edit/delete. */
class ExamTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('grading.view');
    }

    public function view(User $user, ExamType $examType): bool
    {
        return $user->can('grading.view');
    }

    public function create(User $user): bool
    {
        return $user->can('grading.manage');
    }

    public function update(User $user, ExamType $examType): bool
    {
        return $user->can('grading.manage');
    }

    public function delete(User $user, ExamType $examType): bool
    {
        return $user->can('grading.manage');
    }
}
