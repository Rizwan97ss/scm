<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['school_id', 'academic_year_id', 'term_id', 'exam_type_id', 'name', 'weight', 'is_published', 'published_at'])]
class Exam extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    public function examSubjectGroups(): HasMany
    {
        return $this->hasMany(ExamSubjectGroup::class);
    }

    /**
     * The generic `exams.view` permission alone would let a Student/Parent
     * list *every* exam in the school via GET /exams — including unpublished
     * ones and exams for sections they have no relation to. This closes that
     * gap the same way StudentAttendance/ExamMark do it: Student/Parent only
     * ever see published exams touching their own/their child's section;
     * Teacher/Class Teacher see exams touching a section they teach or lead,
     * published or not (they need to see draft exams to enter marks).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            $sectionId = Student::query()->where('user_id', $user->id)->value('current_section_id');

            return $query
                // Phase 16: an exam is listable once it's wholly published OR at
                // least one of its subject groups in the student's own section
                // has been individually declared — matches
                // ParentPortalController::childExams()'s identical composition,
                // so the two portals never disagree on what a family can see.
                ->where(function ($q) use ($sectionId) {
                    $q->where('is_published', true)
                        ->orWhereHas('examSubjectGroups', fn ($q2) => $q2->where('section_id', $sectionId)->whereNotNull('published_at'));
                })
                ->when(
                    $sectionId,
                    fn ($q) => $q->whereHas('examSubjects', fn ($q2) => $q2->where('section_id', $sectionId)),
                    fn ($q) => $q->whereRaw('1 = 0'),
                );
        }

        if ($user->hasRole('Parent')) {
            $sectionIds = Student::query()
                ->whereHas('guardians', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('current_section_id');

            return $query
                ->where(function ($q) use ($sectionIds) {
                    $q->where('is_published', true)
                        ->orWhereHas('examSubjectGroups', fn ($q2) => $q2->whereIn('section_id', $sectionIds)->whereNotNull('published_at'));
                })
                ->whereHas('examSubjects', fn ($q) => $q->whereIn('section_id', $sectionIds));
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
                ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
                ->unique();

            return $query->whereHas('examSubjects', fn ($q) => $q->whereIn('section_id', $sectionIds));
        }

        return $query;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_published'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
