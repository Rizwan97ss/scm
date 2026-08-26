<?php

namespace App\Http\Resources;

use App\Models\OnlineTestAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OnlineTestAttempt
 *
 * Takes an explicit $viewer (mirrors ExamService::reportCard()'s own
 * ?User $viewer = null convention) rather than reading $request->user()
 * implicitly — no resource in this codebase does that today, and doing so
 * here would make the masking below silently depend on this always being
 * rendered inside a live HTTP request/response cycle.
 */
class OnlineTestAttemptResource extends JsonResource
{
    public function __construct($resource, private readonly ?User $viewer = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $canSeeResult = $this->status === 'submitted' && $this->isResultVisibleTo();

        return [
            'id' => $this->id,
            'exam_subject_id' => $this->exam_subject_id,
            'student_id' => $this->student_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            // Score, and the full answer key, are only meaningful once
            // submitted AND the subject's result has actually been declared —
            // matches every other component type's Draft/Calculated/Published
            // gating (see ExamSubjectGroup::status()). Before that, the
            // response still confirms the submission was received, just not
            // graded-and-visible yet.
            'score' => $this->when($canSeeResult, fn () => $this->score),
            'max_score' => $this->when($canSeeResult, fn () => $this->max_score),
            'answers' => $this->when($canSeeResult, fn () => $this->answers->map(fn ($answer) => [
                'question_id' => $answer->question_id,
                'question_text' => $answer->question->text,
                'selected_option_id' => $answer->selected_option_id,
                'correct_option_id' => $answer->question->correctOption()?->id,
                'is_correct' => $answer->is_correct,
                'marks_awarded' => $answer->marks_awarded,
                'explanation' => $answer->question->explanation,
            ])->values()),
        ];
    }

    private function isResultVisibleTo(): bool
    {
        // Staff reviewing via show() — matches every other Phase-16 masking
        // point's staff-vs-Student/Parent split (Student/Parent both also
        // hold exam-marks.view, scoped to their own marks elsewhere).
        if ($this->viewer && ! $this->viewer->hasAnyRole(['Student', 'Parent']) && $this->viewer->can('exam-marks.view')) {
            return true;
        }

        $group = $this->examSubject->examSubjectGroup;

        // The attempt itself IS the mark-producing event — syncExamMark()
        // already ran inside the same submitAttempt() transaction by the
        // time this resource is built, so "has a mark for this student" is
        // simply "this attempt is submitted," no extra query needed.
        return $group->status(true) === 'published' || $group->exam->is_published;
    }
}
