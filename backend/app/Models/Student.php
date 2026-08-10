<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'school_id', 'admission_number', 'user_id', 'first_name', 'last_name', 'gender', 'date_of_birth',
    'blood_group', 'nationality', 'current_grade_level_id', 'current_section_id', 'academic_year_id',
    'roll_number', 'admission_date', 'status', 'previous_school_name', 'previous_school_details',
    'medical_info', 'emergency_contact_name', 'emergency_contact_phone', 'address_line1', 'address_line2',
    'city', 'state', 'postal_code', 'country',
])]
class Student extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    public const DOCUMENT_COLLECTIONS = ['photo', 'birth_certificate', 'previous_report_card', 'transfer_certificate', 'other'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            $student->uuid ??= (string) Str::uuid();
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

    public function currentGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'current_grade_level_id');
    }

    public function currentSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'current_section_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->using(StudentGuardian::class)
            ->withPivot(['relationship_type', 'is_primary', 'can_pickup'])
            ->withTimestamps();
    }

    public function enrollmentHistories(): HasMany
    {
        return $this->hasMany(StudentEnrollmentHistory::class)->latest('effective_date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(StudentAttendance::class);
    }

    public function examMarks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Restricts a Student/Teacher/Class Teacher/Parent query to what that role
     * is allowed to see. Staff roles with a blanket `students.view` permission
     * are left unfiltered by the caller (this scope is only applied for the
     * "Own" tier of the permission matrix).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            return $query->where('user_id', $user->id);
        }

        if ($user->hasRole('Parent')) {
            return $query->whereHas('guardians', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
                ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
                ->unique();

            return $query->whereIn('current_section_id', $sectionIds);
        }

        return $query;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
        $this->addMediaCollection('birth_certificate')->singleFile();
        $this->addMediaCollection('previous_report_card');
        $this->addMediaCollection('transfer_certificate');
        $this->addMediaCollection('other');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'status', 'current_grade_level_id', 'current_section_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
