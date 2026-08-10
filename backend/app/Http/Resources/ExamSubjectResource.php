<?php

namespace App\Http\Resources;

use App\Models\ExamSubject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExamSubject */
class ExamSubjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'section' => $this->whenLoaded('section', fn () => ['id' => $this->section->id, 'name' => $this->section->name]),
            'grading_scale_id' => $this->grading_scale_id,
            'max_marks' => $this->max_marks,
            'passing_marks' => $this->passing_marks,
            'exam_date' => $this->exam_date?->toDateString(),
            'is_online' => $this->is_online,
            'duration_minutes' => $this->duration_minutes,
            'online_starts_at' => $this->online_starts_at?->toIso8601String(),
            'online_ends_at' => $this->online_ends_at?->toIso8601String(),
            'shuffle_questions' => $this->shuffle_questions,
            'max_attempts' => $this->max_attempts,
        ];
    }
}
