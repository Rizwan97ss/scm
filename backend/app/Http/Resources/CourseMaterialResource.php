<?php

namespace App\Http\Resources;

use App\Models\CourseMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CourseMaterial */
class CourseMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'section' => $this->whenLoaded('section', fn () => ['id' => $this->section->id, 'name' => $this->section->name]),
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'teacher' => $this->whenLoaded('teacher', fn () => ['id' => $this->teacher->id, 'full_name' => $this->teacher->full_name]),
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'url' => $this->url,
            'is_published' => $this->is_published,
            'attachments' => $this->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'size' => $media->size,
                'url' => route('api.v1.media.show', $media),
            ]),
            'my_progress' => $this->whenLoaded('progress', fn () => $this->progress->isNotEmpty() ? [
                'viewed_at' => $this->progress->first()->viewed_at?->toIso8601String(),
                'completed_at' => $this->progress->first()->completed_at?->toIso8601String(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
