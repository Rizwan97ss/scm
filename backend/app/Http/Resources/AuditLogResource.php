<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/** @mixin Activity */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'description' => $this->description,
            'event' => $this->event,
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'causer' => $this->causer ? [
                'id' => $this->causer->id,
                'full_name' => $this->causer->full_name ?? null,
            ] : null,
            'changes' => $this->properties,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
