<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'school_id', 'exam_id', 'subject_id', 'section_id', 'grading_scale_id', 'max_marks', 'passing_marks', 'exam_date',
    'is_online', 'duration_minutes', 'online_starts_at', 'online_ends_at', 'shuffle_questions', 'max_attempts',
])]
class ExamSubject extends Model
{
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'max_marks' => 'float',
            'passing_marks' => 'float',
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

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
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
