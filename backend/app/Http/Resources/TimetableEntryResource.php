<?php

namespace App\Http\Resources;

use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimetableEntry */
class TimetableEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'section_id' => $this->section_id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? ['id' => $this->subject->id, 'name' => $this->subject->name] : null),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? ['id' => $this->teacher->id, 'full_name' => $this->teacher->full_name] : null),
            'room' => $this->whenLoaded('room', fn () => $this->room ? ['id' => $this->room->id, 'name' => $this->room->name] : null),
            'timetable_period_id' => $this->timetable_period_id,
            'period' => $this->whenLoaded('period', fn () => ['id' => $this->period->id, 'name' => $this->period->name, 'start_time' => $this->period->start_time, 'end_time' => $this->period->end_time]),
            'day_of_week' => $this->day_of_week?->value,
        ];
    }
}
