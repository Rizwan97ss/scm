<?php

namespace App\Policies;

use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\User;

/**
 * Previously had no dedicated Policy at all — access to a submission (and,
 * critically, its file attachments once MediaController started gating
 * every upload through Gate::authorize('view', $media->model)) was only
 * ever checked indirectly, via the parent Homework's policy in
 * HomeworkSubmissionController. That's fine for the controller's own
 * routes (index/grade both explicitly authorize the Homework first), but
 * MediaController needs a real Policy::view() to call. Mirrors exactly who
 * that controller already lets see a submission: the owning student
 * (Student::scopeVisibleTo also covers their guardians), or a
 * teacher/class teacher who actually teaches the parent homework's
 * subject+section.
 */
class HomeworkSubmissionPolicy
{
    public function view(User $user, HomeworkSubmission $submission): bool
    {
        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return true;
        }

        if ($submission->homework?->isTaughtBy($user)) {
            return true;
        }

        return Student::query()->visibleTo($user)->whereKey($submission->student_id)->exists();
    }
}
