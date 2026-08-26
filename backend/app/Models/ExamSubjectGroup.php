<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per subject-in-section-in-exam — the "unified result" grouping
 * above ExamSubject, which now represents a single gradable component
 * (Online MCQ, Written, Practical, Oral, ...) instead of the whole
 * subject. See SubjectResultService for the actual mark/total/grade
 * computation; this model only owns the publish decision.
 */
#[Fillable(['school_id', 'exam_id', 'subject_id', 'section_id', 'grading_scale_id', 'passing_marks', 'published_at', 'published_by'])]
class ExamSubjectGroup extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'passing_marks' => 'float',
            'published_at' => 'datetime',
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

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** This group's gradable components — one row per assessment type (Online MCQ, Written, ...). */
    public function components(): HasMany
    {
        return $this->hasMany(ExamSubject::class, 'exam_subject_group_id')->orderBy('sequence');
    }

    /**
     * Published ⇔ this group was declared individually, OR the whole exam
     * was — the two compose by OR (see ExamService::publish(), which stamps
     * every group's published_at when the whole exam is published, so this
     * check alone is sufficient without a separate exam lookup in the
     * common case). Calculated ⇔ not published but at least one component
     * has a mark for the given student. Draft ⇔ neither.
     *
     * Caller must eager-load `components.marks` filtered to the student in
     * question (or pass an already-known "has any mark" flag) — this method
     * intentionally takes that as a plain bool rather than a Student, so it
     * has no query of its own and stays cheap to call per-row in a list.
     */
    public function status(bool $hasAnyMarkForStudent): string
    {
        if ($this->published_at !== null) {
            return 'published';
        }

        return $hasAnyMarkForStudent ? 'calculated' : 'draft';
    }
}
