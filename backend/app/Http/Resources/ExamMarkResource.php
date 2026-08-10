<?php

namespace App\Http\Resources;

use App\Models\ExamMark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExamMark */
class ExamMarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_subject_id' => $this->exam_subject_id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'admission_number' => $this->student->admission_number,
            ]),
            'marks_obtained' => $this->marks_obtained,
            'is_absent' => $this->is_absent,
            'percentage' => $this->percentage,
            'remarks' => $this->remarks,
            'entered_by' => $this->whenLoaded('enteredBy', fn () => $this->enteredBy ? ['id' => $this->enteredBy->id, 'full_name' => $this->enteredBy->full_name] : null),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
