<?php

namespace App\Http\Resources;

use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StaffAttendance */
class StaffAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'roles' => $this->user->getRoleNames(),
            ]),
            'date' => $this->date?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'remarks' => $this->remarks,
            'marked_by' => $this->whenLoaded('markedBy', fn () => $this->markedBy ? ['id' => $this->markedBy->id, 'full_name' => $this->markedBy->full_name] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
