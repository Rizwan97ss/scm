<?php

namespace App\Policies;

use App\Models\ClassSubjectTeacher;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class QuestionPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'questions';

    /**
     * Overrides BaseModulePolicy's permission-only check — view/update/
     * delete additionally require actually owning or teaching this
     * question, mirroring Question::scopeVisibleTo() for the single-record
     * case (QuestionController::update()/destroy() go through this, not
     * the scope, since they're resolved by route-model-binding before any
     * query filtering happens). Parameter stays typed as the parent's
     * `Model` (PHP forbids narrowing a parameter type in an override) —
     * always a Question in practice, since this policy is only ever
     * resolved for that model.
     */
    public function view(User $user, Model $question): bool
    {
        return $user->can('questions.view') && $this->ownsOrTeaches($user, $question);
    }

    public function update(User $user, Model $question): bool
    {
        return $user->can('questions.edit') && $this->ownsOrTeaches($user, $question);
    }

    public function delete(User $user, Model $question): bool
    {
        return $user->can('questions.delete') && $this->ownsOrTeaches($user, $question);
    }

    public function import(User $user): bool
    {
        return $user->can('questions.import');
    }

    private function ownsOrTeaches(User $user, Question $question): bool
    {
        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return true;
        }

        if ($question->created_by === $user->id) {
            return true;
        }

        return ClassSubjectTeacher::query()
            ->where('teacher_id', $user->id)
            ->where('subject_id', $question->subject_id)
            ->exists();
    }
}
