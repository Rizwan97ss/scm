<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $user->can('students.view')
            && Student::query()->whereKey($student->id)->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('students.create');
    }

    public function update(User $user, Student $student): bool
    {
        if ($user->can('students.edit')) {
            return true;
        }

        return $user->hasRole('Class Teacher') && $student->currentSection?->class_teacher_id === $user->id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.delete');
    }

    public function manageEnrollment(User $user, ?Student $student = null): bool
    {
        return $user->can('enrollment.manage');
    }

    public function import(User $user): bool
    {
        return $user->can('students.import');
    }

    public function export(User $user): bool
    {
        return $user->can('students.export');
    }
}
