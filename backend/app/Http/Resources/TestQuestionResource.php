<?php

namespace App\Http\Resources;

use App\Models\OnlineTestQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a student sees while an attempt is in_progress — deliberately omits
 * is_correct and explanation so the answer key never reaches the browser
 * mid-test. Use QuestionResource for question-bank management instead.
 *
 * @mixin OnlineTestQuestion
 */
class TestQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->question->id,
            'type' => $this->question->type,
            'text' => $this->question->text,
            'marks' => $this->effectiveMarks(),
            'options' => $this->question->options->map(fn ($option) => [
                'id' => $option->id,
                'option_text' => $option->option_text,
            ])->values(),
        ];
    }
}
