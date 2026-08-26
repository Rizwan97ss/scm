<?php

namespace App\Http\Resources;

use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Visitor */
class VisitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'purpose' => $this->purpose,
            'whom_to_meet' => $this->whom_to_meet,
            'check_in_time' => $this->check_in_time?->toIso8601String(),
            'check_out_time' => $this->check_out_time?->toIso8601String(),
            'notes' => $this->notes,
            'logged_by' => $this->whenLoaded('loggedBy', fn () => ['id' => $this->loggedBy->id, 'full_name' => $this->loggedBy->full_name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
