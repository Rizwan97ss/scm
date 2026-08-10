<?php

namespace App\Http\Resources;

use App\Models\HostelAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HostelAllocation */
class HostelAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'full_name' => $this->student->full_name]),
            'hostel_room' => $this->whenLoaded('hostelRoom', fn () => [
                'id' => $this->hostelRoom->id,
                'room_number' => $this->hostelRoom->room_number,
                'hostel' => $this->hostelRoom->relationLoaded('hostel') ? ['id' => $this->hostelRoom->hostel->id, 'name' => $this->hostelRoom->hostel->name] : null,
            ]),
            'bed_number' => $this->bed_number,
            'allocated_date' => $this->allocated_date?->toDateString(),
            'vacated_date' => $this->vacated_date?->toDateString(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
