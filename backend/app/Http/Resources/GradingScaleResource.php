<?php

namespace App\Http\Resources;

use App\Models\GradingScale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GradingScale */
class GradingScaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'grade_bands' => GradeBandResource::collection($this->whenLoaded('gradeBands')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
