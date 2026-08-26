<?php

namespace App\Http\Requests\Exam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncOnlineTestQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_id' => ['required', 'distinct', Rule::exists('questions', 'id')],
            'questions.*.marks' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
