<?php

namespace App\Policies;

use App\Models\User;

class QuestionPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'questions';

    public function import(User $user): bool
    {
        return $user->can('questions.import');
    }
}
