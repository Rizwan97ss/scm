<?php

namespace App\Http\Resources;

use App\Models\HostelRoom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HostelRoom */
class HostelRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hostel' => $this->whenLoaded('hostel', fn () => ['id' => $this->hostel->id, 'name' => $this->hostel->name]),
            'room_number' => $this->room_number,
            'capacity' => $this->capacity,
            'occupied_count' => $this->occupiedCount(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
