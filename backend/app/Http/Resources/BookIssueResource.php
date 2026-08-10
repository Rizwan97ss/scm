<?php

namespace App\Http\Resources;

use App\Models\BookIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookIssue */
class BookIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book' => $this->whenLoaded('book', fn () => ['id' => $this->book->id, 'title' => $this->book->title]),
            'student' => $this->whenLoaded('student', fn () => $this->student ? ['id' => $this->student->id, 'full_name' => $this->student->full_name] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'full_name' => $this->user->full_name] : null),
            'borrower_name' => $this->borrower_name,
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'return_date' => $this->return_date?->toDateString(),
            'fine_amount' => $this->fine_amount,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
