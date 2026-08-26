<?php

namespace App\Policies;

use App\Models\Homework;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class HomeworkPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'homework';

    /**
     * Overrides BaseModulePolicy's permission-only check — homework.view is
     * held by Student/Parent too, so without composing with
     * Homework::scopeVisibleTo() here, any authenticated Student/Parent could
     * view (and, via MediaController, download the attachments of) homework
     * belonging to a section that isn't theirs/their child's. Parameter
     * stays typed as the parent's `Model` (PHP forbids narrowing a
     * parameter type in an override).
     */
    public function view(User $user, Model $homework): bool
    {
        return $user->can('homework.view')
            && Homework::query()->whereKey($homework->id)->visibleTo($user)->exists();
    }

    public function grade(User $user, Homework $homework): bool
    {
        return $user->can('homework.grade');
    }
}
