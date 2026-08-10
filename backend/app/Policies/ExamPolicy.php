<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'exams';

    public function publish(User $user, Exam $exam): bool
    {
        return $user->can('exams.publish');
    }
}
