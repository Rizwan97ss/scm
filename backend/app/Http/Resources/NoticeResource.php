<?php

namespace App\Http\Resources;

use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notice */
class NoticeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'audience' => $this->audience->value,
            'audience_label' => $this->audience->label(),
            'event_date' => $this->event_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location,
            'is_published' => $this->is_published,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toDateString(),
            'created_by' => $this->whenLoaded('createdBy', fn () => ['id' => $this->createdBy->id, 'full_name' => $this->createdBy->full_name]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
