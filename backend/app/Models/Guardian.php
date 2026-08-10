<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'school_id', 'user_id', 'first_name', 'last_name', 'email', 'phone', 'occupation',
    'national_id', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country', 'invited_at',
])]
class Guardian extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Guardian $guardian) {
            $guardian->uuid ??= (string) Str::uuid();
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
            ->using(StudentGuardian::class)
            ->withPivot(['relationship_type', 'is_primary', 'can_pickup'])
            ->withTimestamps();
    }

    /**
     * @see Student::scopeVisibleTo() for the mirrored rationale.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Parent')) {
            return $query->where('user_id', $user->id);
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            return $query->whereHas('students', fn ($q) => $q->visibleTo($user));
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
