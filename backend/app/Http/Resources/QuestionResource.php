<?php

namespace App\Http\Resources;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Question */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject ? ['id' => $this->subject->id, 'name' => $this->subject->name] : null),
            'type' => $this->type,
            'text' => $this->text,
            'default_marks' => $this->default_marks,
            'negative_marks' => $this->negative_marks,
            'explanation' => $this->explanation,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
