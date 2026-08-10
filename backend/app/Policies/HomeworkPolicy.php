<?php

namespace App\Policies;

use App\Models\Homework;
use App\Models\User;

class HomeworkPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'homework';

    public function grade(User $user, Homework $homework): bool
    {
        return $user->can('homework.grade');
    }
}
