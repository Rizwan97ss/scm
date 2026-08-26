<?php

namespace App\Http\Resources;

use App\Models\HomeworkSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HomeworkSubmission */
class HomeworkSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'homework_id' => $this->homework_id,
            'student_id' => $this->student_id,
            'student' => $this->whenLoaded('student', fn () => ['id' => $this->student->id, 'full_name' => $this->student->full_name, 'admission_number' => $this->student->admission_number]),
            'status' => $this->status->value,
            'content' => $this->content,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'score' => $this->score,
            'feedback' => $this->feedback,
            'graded_at' => $this->graded_at?->toIso8601String(),
            'graded_by' => $this->whenLoaded('gradedBy', fn () => $this->gradedBy ? ['id' => $this->gradedBy->id, 'full_name' => $this->gradedBy->full_name] : null),
            'attachments' => $this->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'size' => $media->size,
                'url' => route('api.v1.media.show', $media),
            ]),
        ];
    }
}
