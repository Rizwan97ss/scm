<?php

namespace App\Traits;

use App\Models\School;
use App\Scopes\SchoolScope;

/**
 * Apply to any Eloquent model with a `school_id` column to make it tenant-scoped:
 * queries are automatically filtered to the current user's school, and new records
 * are automatically stamped with it, unless the acting user is a Super Admin
 * (school_id === null), who must set school_id explicitly.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function ($model) {
            if ($model->school_id === null && auth()->check() && auth()->user()->school_id !== null) {
                $model->school_id = auth()->user()->school_id;
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function scopeWithoutSchoolScope($query)
    {
        return $query->withoutGlobalScope(SchoolScope::class);
    }
}
