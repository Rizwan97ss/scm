<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id', 'exam_id', 'exam_subject_group_id', 'assessment_component_type_id', 'subject_id', 'section_id',
    'sequence', 'max_marks', 'exam_date',
    'is_online', 'duration_minutes', 'online_starts_at', 'online_ends_at', 'shuffle_questions', 'max_attempts',
    'early_access_minutes', 'late_join_grace_minutes',
])]
class ExamSubject extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'max_marks' => 'float',
            'exam_date' => 'date',
            'is_online' => 'boolean',
            'online_starts_at' => 'datetime',
            'online_ends_at' => 'datetime',
            'shuffle_questions' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** The subject-in-section-in-exam this component belongs under — owns grading_scale_id/passing_marks/publish state. */
    public function examSubjectGroup(): BelongsTo
    {
        return $this->belongsTo(ExamSubjectGroup::class, 'exam_subject_group_id');
    }

    public function assessmentComponentType(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponentType::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function onlineTestQuestions(): HasMany
    {
        return $this->hasMany(OnlineTestQuestion::class)->orderBy('sequence');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OnlineTestAttempt::class);
    }

    /**
     * A Teacher (not Class Teacher/Admin) may only enter marks for a subject
     * they're actually assigned to teach in this exact section — mirrors
     * StudentAttendanceController::assertSectionMarkable()'s rationale.
     */
    public function isTaughtBy(User $user): bool
    {
        return ClassSubjectTeacher::query()
            ->where('section_id', $this->section_id)
            ->where('subject_id', $this->subject_id)
            ->where('teacher_id', $user->id)
            ->exists();
    }
}
