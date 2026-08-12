<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Models\Concerns\HasTwoFactorAuthentication;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'school_id', 'first_name', 'last_name', 'email', 'username', 'phone',
    'gender', 'date_of_birth', 'status', 'must_change_password', 'password',
    'designation_id', 'employee_id', 'hire_date',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasTwoFactorAuthentication, InteractsWithMedia, LogsActivity, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'status' => UserStatus::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'hire_date' => 'date',
            'phone' => 'encrypted',
            ...$this->twoFactorCasts(),
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->uuid ??= (string) Str::uuid();
            $user->mfa_grace_period_ends_at ??= now();
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Intentionally NOT using the BelongsToSchool trait/global SchoolScope here:
     * the auth guard resolves $request->user() by querying this exact model, and
     * SchoolScope::apply() calls auth()->user() internally — applied to User that
     * becomes infinite recursion (resolving the user requires the user). Callers
     * that need school-scoped user lists (e.g. UserController@index) must apply
     * this scope explicitly instead.
     */
    public function scopeInSchool(Builder $query, ?int $schoolId): Builder
    {
        return $schoolId === null ? $query : $query->where('school_id', $schoolId);
    }

    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function staffAttendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /** "Staff" = any role other than Student/Parent — see School::staffCount()'s docblock, the one other place this same idea was needed. */
    public function isStaff(): bool
    {
        return ! $this->hasAnyRole(['Student', 'Parent']);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('documents');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'status', 'school_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
