<?php

namespace App\Http\Resources;

use App\Models\ExamSubject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One gradable component (Online MCQ, Written, ...) under an ExamSubjectGroup. @mixin ExamSubject */
class ExamSubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam_subject_group_id' => $this->exam_subject_group_id,
            'assessment_component_type' => $this->whenLoaded(
                'assessmentComponentType',
                fn () => $this->assessmentComponentType ? ['id' => $this->assessmentComponentType->id, 'name' => $this->assessmentComponentType->name, 'is_auto_graded' => $this->assessmentComponentType->is_auto_graded] : null
            ),
            'sequence' => $this->sequence,
            'max_marks' => $this->max_marks,
            'exam_date' => $this->exam_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'is_online' => $this->is_online,
            'duration_minutes' => $this->duration_minutes,
            'online_starts_at' => $this->online_starts_at?->toIso8601String(),
            'online_ends_at' => $this->online_ends_at?->toIso8601String(),
            'shuffle_questions' => $this->shuffle_questions,
            'max_attempts' => $this->max_attempts,
        ];
    }
}
