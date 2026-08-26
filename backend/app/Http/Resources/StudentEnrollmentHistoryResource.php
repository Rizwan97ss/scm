<?php

namespace App\Http\Resources;

use App\Models\StudentEnrollmentHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentEnrollmentHistory */
class StudentEnrollmentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action?->value,
            'from_grade_level' => $this->whenLoaded('fromGradeLevel', fn () => $this->fromGradeLevel?->name),
            'to_grade_level' => $this->whenLoaded('toGradeLevel', fn () => $this->toGradeLevel?->name),
            'from_section' => $this->whenLoaded('fromSection', fn () => $this->fromSection?->name),
            'to_section' => $this->whenLoaded('toSection', fn () => $this->toSection?->name),
            'reason' => $this->reason,
            'effective_date' => $this->effective_date?->toDateString(),
            'performed_by' => $this->whenLoaded('performedBy', fn () => $this->performedBy?->full_name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
