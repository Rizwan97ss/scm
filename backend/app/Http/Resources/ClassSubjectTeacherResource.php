<?php

namespace App\Http\Resources;

use App\Models\ClassSubjectTeacher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClassSubjectTeacher */
class ClassSubjectTeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'section_id' => $this->section_id,
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'teacher' => $this->whenLoaded('teacher', fn () => ['id' => $this->teacher->id, 'full_name' => $this->teacher->full_name]),
        ];
    }
}
