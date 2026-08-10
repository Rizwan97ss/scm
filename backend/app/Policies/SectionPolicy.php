<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SectionPolicy extends BaseModulePolicy
{
    protected string $permissionPrefix = 'academic-structure';

    /**
     * A Class Teacher may update their own section even without the
     * blanket academic-structure.edit permission (e.g. reassigning its room).
     *
     * Parameter type must match BaseModulePolicy::update()'s (Model, not
     * Section) — narrowing it previously violated PHP's parameter-type
     * contravariance rule, which PHP enforces as a fatal error the moment
     * this class is loaded (not just when update() is actually called). That
     * made every request touching SectionPolicy at all — including a plain
     * GET /sections — fail, and it went uncaught because no test exercised
     * Section endpoints through the HTTP layer (see tests/Feature/Academic/,
     * which only covers AcademicYear and the timetable-conflict rule).
     */
    public function update(User $user, Model $model): bool
    {
        return $user->can('academic-structure.edit') || $model->class_teacher_id === $user->id;
    }
}
