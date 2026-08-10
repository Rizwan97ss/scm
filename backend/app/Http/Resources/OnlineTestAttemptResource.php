<?php

namespace App\Http\Resources;

use App\Models\OnlineTestAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OnlineTestAttempt */
class OnlineTestAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_subject_id' => $this->exam_subject_id,
            'student_id' => $this->student_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'score' => $this->score,
            'max_score' => $this->max_score,
            // Only meaningful once submitted — the answer key must not leak while in_progress.
            'answers' => $this->when($this->status === 'submitted', fn () => $this->answers->map(fn ($answer) => [
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
}
