<?php

namespace App\Http\Resources;

use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Includes is_correct — for question-bank management views only. Never
 * serve this to a student mid-attempt; use TestQuestionResource instead.
 *
 * @mixin QuestionOption
 */
class QuestionOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'option_text' => $this->option_text,
            'is_correct' => $this->is_correct,
            'sequence' => $this->sequence,
        ];
    }
}
