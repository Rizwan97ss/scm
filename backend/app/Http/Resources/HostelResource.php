<?php

namespace App\Http\Resources;

use App\Models\Hostel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Hostel */
class HostelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'address' => $this->address,
            'warden_name' => $this->warden_name,
            'warden_phone' => $this->warden_phone,
            'is_active' => $this->is_active,
            'room_count' => $this->whenLoaded('rooms', fn () => $this->rooms->count()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
