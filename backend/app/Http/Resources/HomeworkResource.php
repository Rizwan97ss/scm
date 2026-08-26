<?php

namespace App\Http\Resources;

use App\Models\Homework;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Homework */
class HomeworkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'section' => $this->whenLoaded('section', fn () => ['id' => $this->section->id, 'name' => $this->section->name]),
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'teacher' => $this->whenLoaded('teacher', fn () => ['id' => $this->teacher->id, 'full_name' => $this->teacher->full_name]),
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),
            'max_score' => $this->max_score,
            'attachments' => $this->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'size' => $media->size,
                'url' => route('api.v1.media.show', $media),
            ]),
            'my_submission' => $this->whenLoaded('submissions', fn () => $this->submissions->isNotEmpty() ? new HomeworkSubmissionResource($this->submissions->first()) : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
