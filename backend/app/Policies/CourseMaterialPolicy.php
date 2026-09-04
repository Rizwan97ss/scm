<?php

namespace App\Policies;

use App\Models\CourseMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CourseMaterialPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'course-materials';

    /** Same reasoning as HomeworkPolicy::view() — composes with scopeVisibleTo() so a Student/Parent can't view another section's material by guessing an id. */
    public function view(User $user, Model $courseMaterial): bool
    {
        return $user->can('course-materials.view')
            && CourseMaterial::query()->whereKey($courseMaterial->id)->visibleTo($user)->exists();
    }
}
