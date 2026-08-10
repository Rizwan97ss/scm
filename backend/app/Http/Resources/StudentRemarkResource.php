<?php

namespace App\Http\Resources;

use App\Models\StudentRemark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentRemark */
class StudentRemarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'author' => $this->whenLoaded('author', fn () => ['id' => $this->author->id, 'full_name' => $this->author->full_name]),
            'category' => $this->category->value,
            'body' => $this->body,
            'visible_to_guardian' => $this->visible_to_guardian,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
