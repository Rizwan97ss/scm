<?php

namespace App\Http\Resources;

use App\Models\DataExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DataExport */
class DataExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope->value,
            'status' => $this->status->value,
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy?->full_name),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
