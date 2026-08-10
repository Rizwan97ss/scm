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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['school_id', 'academic_year_id', 'section_id', 'subject_id', 'teacher_id', 'title', 'description', 'due_date', 'max_score'])]
class Homework extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    // "Homework" is grammatically uncountable, so Eloquent's automatic
    // pluralization guesses the table name as `homework` (unchanged), not
    // `homeworks` — declared explicitly to match the migration.
    protected $table = 'homeworks';

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'max_score' => 'float',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    /**
     * Same shape as Exam::scopeVisibleTo(): Student/Parent only ever see
     * homework touching their own/their child's current section; Teacher/
     * Class Teacher see homework for a section they teach or lead (they need
     * to see a colleague's assignment for their homeroom section, not just
     * their own subject's).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Student')) {
            $sectionId = Student::query()->where('user_id', $user->id)->value('current_section_id');

            return $query->when($sectionId, fn ($q) => $q->where('section_id', $sectionId), fn ($q) => $q->whereRaw('1 = 0'));
        }

        if ($user->hasRole('Parent')) {
            $sectionIds = Student::query()
                ->whereHas('guardians', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('current_section_id');

            return $query->whereIn('section_id', $sectionIds);
        }

        if ($user->hasAnyRole(['Teacher', 'Class Teacher'])) {
            $sectionIds = Section::query()->where('class_teacher_id', $user->id)->pluck('id')
                ->merge(ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('section_id'))
                ->unique();

            return $query->whereIn('section_id', $sectionIds);
        }

        return $query;
    }

    /**
     * A Teacher (not Class Teacher/Admin) may only create/edit homework for a
     * subject they're actually assigned to teach in this exact section —
     * mirrors ExamSubject::isTaughtBy().
     */
    public function isTaughtBy(User $user): bool
    {
        return ClassSubjectTeacher::query()
            ->where('section_id', $this->section_id)
            ->where('subject_id', $this->subject_id)
            ->where('teacher_id', $user->id)
            ->exists();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'due_date', 'max_score'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
