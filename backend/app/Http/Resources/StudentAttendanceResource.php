<?php

namespace App\Http\Resources;

use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentAttendance */
class StudentAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
                'admission_number' => $this->student->admission_number,
            ]),
            'section_id' => $this->section_id,
            'section' => $this->whenLoaded('section', fn () => $this->section ? ['id' => $this->section->id, 'name' => $this->section->name] : null),
            'academic_year_id' => $this->academic_year_id,
            'timetable_period_id' => $this->timetable_period_id,
            'period' => $this->whenLoaded('timetablePeriod', fn () => $this->timetablePeriod ? ['id' => $this->timetablePeriod->id, 'name' => $this->timetablePeriod->name] : null),
            'date' => $this->date?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'remarks' => $this->remarks,
            'marked_by' => $this->whenLoaded('markedBy', fn () => $this->markedBy ? ['id' => $this->markedBy->id, 'full_name' => $this->markedBy->full_name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
