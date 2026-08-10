<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'exam_subject_id', 'student_id', 'marks_obtained', 'is_absent', 'remarks', 'entered_by'])]
class ExamMark extends Model
{
    use BelongsToSchool, HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'float',
            'is_absent' => 'boolean',
        ];
    }

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function getPercentageAttribute(): ?float
    {
        if ($this->is_absent || $this->marks_obtained === null || ! $this->examSubject?->max_marks) {
            return null;
        }

        return round(($this->marks_obtained / $this->examSubject->max_marks) * 100, 2);
    }

    /**
     * Students/Parents only ever see marks for a *published* exam — Teachers
     * and Class Teachers see everything for their own subjects/sections
     * regardless of publish state, since they're the ones producing it.
     *
     * @see StudentAttendance::scopeVisibleTo() for the mirrored rationale.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            return $query->whereHas('student', fn ($q) => $q->where('user_id', $user->id))
                ->whereHas('examSubject.exam', fn ($q) => $q->where('is_published', true));
        }

        if ($user->hasRole('Parent')) {
            return $query->whereHas('student.guardians', fn ($q) => $q->where('user_id', $user->id))
                ->whereHas('examSubject.exam', fn ($q) => $q->where('is_published', true));
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $classTeacherSectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id');
            $subjectTeacherPairs = ClassSubjectTeacher::query()->where('teacher_id', $user->id)->get(['section_id', 'subject_id']);

            $examSubjectIds = ExamSubject::query()
                ->where(function ($q) use ($classTeacherSectionIds, $subjectTeacherPairs) {
                    $q->whereIn('section_id', $classTeacherSectionIds);

                    foreach ($subjectTeacherPairs as $pair) {
                        $q->orWhere(fn ($q2) => $q2->where('section_id', $pair->section_id)->where('subject_id', $pair->subject_id));
                    }
                })
                ->pluck('id');

            return $query->whereIn('exam_subject_id', $examSubjectIds);
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['marks_obtained', 'is_absent', 'remarks'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
