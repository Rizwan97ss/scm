<?php

namespace App\Http\Resources;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Exam */
class ExamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'term_id' => $this->term_id,
            'name' => $this->name,
            'weight' => $this->weight,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'exam_subjects' => ExamSubjectResource::collection($this->whenLoaded('examSubjects')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
