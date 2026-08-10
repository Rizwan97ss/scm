<?php

namespace App\Http\Resources;

use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'grade_level_id' => $this->grade_level_id,
            'grade_level' => $this->whenLoaded('gradeLevel', fn () => [
                'id' => $this->gradeLevel->id,
                'name' => $this->gradeLevel->name,
            ]),
            'name' => $this->name,
            'capacity' => $this->capacity,
            'student_count' => $this->whenCounted('students'),
            'class_teacher_id' => $this->class_teacher_id,
            'class_teacher' => $this->whenLoaded('classTeacher', fn () => $this->classTeacher ? [
                'id' => $this->classTeacher->id,
                'full_name' => $this->classTeacher->full_name,
            ] : null),
            'room_id' => $this->room_id,
            'room' => $this->whenLoaded('room', fn () => $this->room ? [
                'id' => $this->room->id,
                'name' => $this->room->name,
            ] : null),
        ];
    }
}
