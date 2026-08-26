<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['school_id', 'subject_id', 'type', 'text', 'default_marks', 'negative_marks', 'explanation', 'created_by'])]
class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'default_marks' => 'float',
            'negative_marks' => 'float',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sequence');
    }

    public function correctOption(): ?QuestionOption
    {
        return $this->options->first(fn (QuestionOption $option) => $option->is_correct);
    }

    /**
     * The blanket `questions.view` permission alone would let any Teacher
     * list/read every other teacher's question bank via GET /questions —
     * including QuestionOptionResource's answer key. Scoped to questions
     * the caller authored themselves or actually teaches the subject of
     * (via ClassSubjectTeacher, subject-wide — a question bank isn't tied
     * to one section, unlike Exam/Homework). School Admin/Principal/Super
     * Admin see everything, same convention as every other module's
     * scopeVisibleTo(). See QuestionPolicy for the single-record mirror of
     * this same check.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['School Admin', 'Principal', 'Super Admin'])) {
            return $query;
        }

        $subjectIds = ClassSubjectTeacher::query()->where('teacher_id', $user->id)->pluck('subject_id')->unique();

        return $query->where(function (Builder $q) use ($user, $subjectIds) {
            $q->where('created_by', $user->id)->orWhereIn('subject_id', $subjectIds);
        });
    }
}
