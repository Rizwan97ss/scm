<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-scoped model to the authenticated user's school.
 * Super Admins (school_id === null on the user) are cross-school and see everything.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        if ($user->school_id === null) {
            return;
        }

        $builder->where($model->getTable().'.school_id', $user->school_id);
    }
}
