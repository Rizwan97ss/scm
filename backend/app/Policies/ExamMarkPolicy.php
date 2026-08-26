<?php

namespace App\Policies;

use App\Models\ExamMark;
use App\Models\User;

class ExamMarkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exam-marks.view');
    }

    public function view(User $user, ExamMark $examMark): bool
    {
        return $user->can('exam-marks.view')
            && ExamMark::query()->whereKey($examMark->id)->visibleTo($user)->exists();
    }

    /**
     * Entering marks is a bulk operation checked against the target
     * ExamSubject rather than a single model instance — see
     * ExamMarkController@store, which also verifies the acting Teacher
     * actually teaches that subject/section via ExamSubject::isTaughtBy().
     */
    public function enter(User $user): bool
    {
        return $user->can('exam-marks.enter');
    }

    public function update(User $user, ExamMark $examMark): bool
    {
        return $user->can('exam-marks.edit')
            && ExamMark::query()->whereKey($examMark->id)->visibleTo($user)->exists();
    }

    public function export(User $user): bool
    {
        return $user->can('exam-marks.export');
    }

    public function import(User $user): bool
    {
        return $user->can('exam-marks.import');
    }
}
