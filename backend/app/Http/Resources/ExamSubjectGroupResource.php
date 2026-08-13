<?php

namespace App\Http\Resources;

use App\Models\ExamSubjectGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExamSubjectGroup */
class ExamSubjectGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'section' => $this->whenLoaded('section', fn () => ['id' => $this->section->id, 'name' => $this->section->name]),
            'grading_scale_id' => $this->grading_scale_id,
            'passing_marks' => $this->passing_marks,
            'published_at' => $this->published_at?->toIso8601String(),
            'components' => ExamSubjectResource::collection($this->whenLoaded('components')),
        ];
    }
}
