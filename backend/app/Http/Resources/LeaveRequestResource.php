<?php

namespace App\Http\Resources;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveRequest */
class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'full_name' => $this->user->full_name]),
            'leave_type' => $this->whenLoaded('leaveType', fn () => ['id' => $this->leaveType->id, 'name' => $this->leaveType->name, 'is_paid' => $this->leaveType->is_paid]),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => $this->days,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? ['id' => $this->reviewedBy->id, 'full_name' => $this->reviewedBy->full_name] : null),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
