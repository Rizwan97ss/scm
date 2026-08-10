<?php

namespace App\Http\Resources;

use App\Models\Route;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Route */
class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'stops' => RouteStopResource::collection($this->whenLoaded('stops')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
