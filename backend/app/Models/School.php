<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

#[Fillable([
    'name', 'short_name', 'slug', 'email', 'phone', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'country', 'timezone', 'locale', 'is_active',
    'plan_id', 'billing_status', 'trial_ends_at',
])]
class School extends Model
{
    use Billable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            $school->uuid ??= (string) Str::uuid();
            $school->slug ??= Str::slug($school->short_name ?: $school->name);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function studentCount(): int
    {
        return $this->students()->whereNull('deleted_at')->count();
    }

    /**
     * "Staff seat" = a user holding any role except Student/Parent (see
     * docs/roadmap.md's Phase 6 notes). Queries model_has_roles directly
     * with an explicit team_id filter rather than the roles() relation,
     * which would otherwise depend on the CURRENT global permission team
     * context (Spatie's teams mode) — this needs to read another school's
     * usage without mutating that shared context, e.g. from the platform
     * admin console listing many schools at once.
     */
    public function staffCount(): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.school_id', $this->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereNotIn('roles.name', ['Student', 'Parent'])
            ->distinct()
            ->count('model_has_roles.model_id');
    }
}
