<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'school_id', 'student_id', 'section_id', 'academic_year_id', 'timetable_period_id',
    'date', 'status', 'remarks', 'marked_by',
])]
class StudentAttendance extends Model
{
    use BelongsToSchool, HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function timetablePeriod(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * @see Student::scopeVisibleTo() for the mirrored rationale.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            return $query->whereHas('student', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($user->hasRole('Parent')) {
            return $query->whereHas('student.guardians', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
                ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
                ->unique();

            return $query->whereIn('section_id', $sectionIds);
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'remarks'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
