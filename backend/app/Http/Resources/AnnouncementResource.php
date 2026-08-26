<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'audience' => $this->audience->value,
            'audience_label' => $this->audience->label(),
            'channels' => $this->channels,
            'recipient_count' => $this->recipient_count,
            'sent_by' => $this->whenLoaded('sentBy', fn () => ['id' => $this->sentBy->id, 'full_name' => $this->sentBy->full_name]),
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
